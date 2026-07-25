import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Connexion" />

            <PasskeyVerify />

            {status && (
                <div className="mb-4 rounded-lg bg-[#42D392]/10 px-4 py-3 text-center text-sm font-medium text-[#42D392]">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="email"
                                    className="text-sm font-medium text-[#F5F8FC]"
                                >
                                    Adresse e-mail
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="vous@exemple.com"
                                    className="border-[#35506D] bg-[#173251] text-[#F5F8FC] placeholder:text-[#53657D] focus:border-[#4FA3FF]"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <div className="flex items-center justify-between">
                                    <Label
                                        htmlFor="password"
                                        className="text-sm font-medium text-[#F5F8FC]"
                                    >
                                        Mot de passe
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="text-xs text-[#4FA3FF]"
                                            tabIndex={5}
                                        >
                                            Mot de passe oublié ?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Mot de passe"
                                    className="border-[#35506D] bg-[#173251] text-[#F5F8FC] placeholder:text-[#53657D] focus:border-[#4FA3FF]"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label
                                    htmlFor="remember"
                                    className="text-sm text-[#A9B7C8]"
                                >
                                    Rester connecté
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-1 w-full bg-[#075CCF] font-semibold hover:bg-[#0A4FAF]"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Se connecter
                            </Button>
                        </div>

                        <p className="text-center text-sm text-[#A9B7C8]">
                            Pas encore de compte ?{' '}
                            <TextLink
                                href={register()}
                                tabIndex={6}
                                className="text-[#4FA3FF]"
                            >
                                Créer un compte
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Connexion',
    description: 'Entrez vos identifiants pour accéder à votre espace Wasplex',
};
