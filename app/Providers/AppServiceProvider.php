<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configurePasswordResetMail();
    }

    /**
     * Configure rate limiting.
     */
    protected function configureRateLimiting(): void
    {
        // Ojo: en `shipments.show` la IP es siempre 127.0.0.1, porque quien llama
        // a la API es el propio servidor desde el componente del buscador. Por eso
        // el identificador va primero por usuario: la IP solo discrimina si algún
        // día llama un cliente externo.
        RateLimiter::for('show-shipment', fn (Request $request) => Limit::perSecond(3)
            ->by($request->user()?->id ?? $request->ip()));
    }

    /**
     * Configure the password reset notification mail.
     */
    protected function configurePasswordResetMail(): void
    {
        // El correo de "olvidaste tu contraseña" se arma aquí, con el hook que
        // ya trae la notificación de Laravel, en vez de con una Notification
        // propia: lo único que cambia respecto a la del framework es el texto
        // (en español y con el tono del producto). La maqueta es la plantilla
        // de correo de Tracely — `resources/views/vendor/mail`, tema
        // `tracely`, ver config/mail.php.
        ResetPassword::toMailUsing(function (User $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject(__('Restablece tu contraseña de Tracely'))
                ->greeting(__('Hola, :name', ['name' => $notifiable->name]))
                ->line(__('Hemos recibido una solicitud para cambiar la contraseña de tu cuenta de Tracely. Pulsa el botón y elige una nueva.'))
                ->action(__('Crear una contraseña nueva'), $url)
                ->line(__('El enlace caduca en :count minutos y solo se puede usar una vez.', ['count' => $minutes]))
                ->line(__('Si no has pedido tú este cambio, ignora este mensaje: tu contraseña actual sigue siendo válida.'))
                ->salutation(__('Un saludo, el equipo de Tracely'));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
