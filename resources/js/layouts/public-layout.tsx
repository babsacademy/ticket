import { Link } from '@inertiajs/react';
import { Ticket } from 'lucide-react';
import type { ReactNode } from 'react';
import { home, login } from '@/routes';

export default function PublicLayout({
    children,
    wide = false,
}: {
    children: ReactNode;
    wide?: boolean;
}) {
    const containerWidth = wide ? 'max-w-6xl' : 'max-w-4xl';

    return (
        <div className="dark flex min-h-svh flex-col bg-background text-foreground">
            <header className="sticky top-0 z-10 border-b border-border/60 bg-background/80 backdrop-blur">
                <div
                    className={`mx-auto flex ${containerWidth} items-center justify-between px-4 py-4 sm:px-6`}
                >
                    <Link href={home()} className="flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <Ticket className="size-5" />
                        </span>
                        <span className="flex flex-col leading-tight">
                            <span className="text-base font-semibold tracking-tight">
                                TerangaTicket
                            </span>
                            <span className="text-xs text-muted-foreground">
                                La billetterie du Sénégal
                            </span>
                        </span>
                    </Link>

                    <nav className="flex items-center gap-6 text-sm font-medium">
                        <Link
                            href={home()}
                            className="transition-colors hover:text-primary"
                        >
                            Accueil
                        </Link>
                        <Link
                            href={login()}
                            className="transition-colors hover:text-primary"
                        >
                            Connexion
                        </Link>
                    </nav>
                </div>
            </header>

            <main
                className={`mx-auto w-full ${containerWidth} flex-1 px-4 py-8 sm:px-6`}
            >
                {children}
            </main>

            <footer className="border-t border-border/60 px-4 py-6 sm:px-6">
                <p
                    className={`mx-auto text-muted-foreground ${containerWidth} text-center text-xs`}
                >
                    Billetterie sécurisée — Sénégal
                </p>
            </footer>
        </div>
    );
}
