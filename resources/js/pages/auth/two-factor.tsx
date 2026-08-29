import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { resend, verify } from '@/routes/two-factor';

const CODE_LENGTH = 6;

export default function TwoFactor({ status }: { status?: string }) {
    const [code, setCode] = useState<string>('');

    return (
        <>
            <Head title="Vérification en deux étapes" />

            <div className="space-y-6">
                {status && (
                    <div className="text-center text-sm font-medium text-green-600">
                        {status}
                    </div>
                )}

                <Form
                    {...verify.form()}
                    className="space-y-4"
                    resetOnError
                    resetOnSuccess
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                <div className="flex w-full items-center justify-center">
                                    <InputOTP
                                        name="code"
                                        maxLength={CODE_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: CODE_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError message={errors.code} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                Vérifier
                            </Button>
                        </>
                    )}
                </Form>

                <Form {...resend.form()} className="text-center text-sm">
                    {({ processing }) => (
                        <button
                            type="submit"
                            disabled={processing}
                            className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! disabled:cursor-not-allowed disabled:opacity-50 dark:decoration-neutral-500"
                        >
                            Renvoyer le code
                        </button>
                    )}
                </Form>
            </div>
        </>
    );
}

TwoFactor.layout = {
    title: 'Vérification en deux étapes',
    description:
        'Entrez le code à 6 chiffres que nous venons de vous envoyer par e-mail.',
};
