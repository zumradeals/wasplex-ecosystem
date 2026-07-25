import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Créer un compte" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="name"
                                    className="text-sm font-medium text-[#F5F8FC]"
                                >
                                    Nom
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Votre nom"
                                    className="border-[#35506D] bg-[#173251] text-[#F5F8FC] placeholder:text-[#53657D] focus:border-[#4FA3FF]"
                                />
                                <InputError message={errors.name} />
                            </div>

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
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="vous@exemple.com"
                                    className="border-[#35506D] bg-[#173251] text-[#F5F8FC] placeholder:text-[#53657D] focus:border-[#4FA3FF]"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="password"
                                    className="text-sm font-medium text-[#F5F8FC]"
                                >
                                    Mot de passe
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Mot de passe"
                                    passwordrules={passwordRules}
                                    className="border-[#35506D] bg-[#173251] text-[#F5F8FC] placeholder:text-[#53657D] focus:border-[#4FA3FF]"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="password_confirmation"
                                    className="text-sm font-medium text-[#F5F8FC]"
                                >
                                    Confirmer le mot de passe
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirmer le mot de passe"
                                    passwordrules={passwordRules}
                                    className="border-[#35506D] bg-[#173251] text-[#F5F8FC] placeholder:text-[#53657D] focus:border-[#4FA3FF]"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-1 w-full bg-[#C75100] font-semibold hover:bg-[#A84300]"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Créer mon compte
                            </Button>
                        </div>

                        <p className="text-center text-sm text-[#A9B7C8]">
                            Déjà un compte ?{' '}
                            <TextLink
                                href={login()}
                                tabIndex={6}
                                className="text-[#4FA3FF]"
                            >
                                Me connecter
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Créer un compte',
    description: 'Rejoignez Wasplex — la publicité qui vous rémunère directement',
};
