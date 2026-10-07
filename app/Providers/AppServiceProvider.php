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
        RateLimiter::for('show-shipment', fn (Request $request) => Limit::perSecond(3)
            ->by($this->showShipmentThrottleKey($request)));
    }

    /**
     * Who a `shipments.show` request counts against.
     *
     * Con sesión, el usuario. Sin sesión, la IP del visitante; pero quien llama
     * de verdad es casi siempre el buscador desde el propio servidor, así que
     * `$request->ip()` sería la del servidor y todos los anónimos compartirían
     * un único cupo. Por eso el buscador manda la IP real en una cabecera, y
     * solo se acepta si va firmada con `services.internal_api.secret`: sin la
     * firma, cualquiera podría inventarse una IP por petición y saltarse el
     * límite.
     */
    protected function showShipmentThrottleKey(Request $request): string
    {
        if ($request->user() !== null) {
            return 'user:'.$request->user()->getAuthIdentifier();
        }

        $visitorIp = $request->header('X-Tracely-Visitor-Ip');
        $signature = (string) $request->header('X-Tracely-Internal');

        if (is_string($visitorIp) && hash_equals((string) config('services.internal_api.secret'), $signature)) {
            return 'ip:'.$visitorIp;
        }

        return 'ip:'.$request->ip();
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
