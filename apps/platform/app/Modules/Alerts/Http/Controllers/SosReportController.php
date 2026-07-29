<?php

namespace App\Modules\Alerts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Http\Requests\StoreSosReportRequest;
use App\Modules\Alerts\Services\CaseDispatchService;
use App\Modules\Alerts\Services\CaseSubmissionService;
use App\Modules\Alerts\Services\Exceptions\NoEligibleInstitutionException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * SOS (AMD-0007 §2 ; ecosystem/alertes/02 §2, §22) : accessible sans
 * authentification complète — aucune capacité, aucun `AuthorizationGate`
 * ici. Seules la validation de forme (`StoreSosReportRequest`) et la
 * limite de fréquence (middleware `throttle`, route) protègent cette
 * route. Le routage institutionnel est tenté immédiatement mais ne
 * bloque jamais la création : « Wasplex n'invente jamais un routage de
 * complaisance » (ecosystem/alertes/03 §1.1) — l'absence d'institution
 * éligible reste un état honnête, pas une erreur 500.
 */
class SosReportController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly CaseSubmissionService $submissionService,
        private readonly CaseDispatchService $dispatchService,
    ) {}

    public function store(StoreSosReportRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
            $author = $subject->personAccountLink;
        } catch (SubjectResolutionFailedException) {
            $author = null;
        }

        $correlationId = (string) Str::uuid();

        $case = $this->submissionService->reportSos(
            author: $author,
            category: CaseCategory::from($request->string('category')->toString()),
            sourceDescription: $request->string('source_description')->toString(),
            countryCode: $request->string('country_code')->toString(),
            territoryCode: $request->string('territory_code')->toString() ?: null,
            exactLocation: $request->input('exact_location'),
            recallPhone: $request->string('recall_phone')->toString() ?: null,
            locale: $request->string('locale')->toString(),
            idempotencyKey: $request->string('idempotency_key')->toString(),
            correlationId: $correlationId,
        );

        $transmitted = false;

        try {
            $this->dispatchService->routeToEligibleInstitutions($case, $correlationId);
            $transmitted = true;
        } catch (NoEligibleInstitutionException) {
            // État honnête : aucune fausse transmission (AMD-0007 §2, §6).
        }

        return response()->json([
            'case_id' => $case->id,
            'state' => $case->state,
            'routed' => $transmitted,
        ], 201);
    }
}
