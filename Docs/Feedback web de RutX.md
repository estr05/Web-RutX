# Feedback web de RutX

**Propósito.** Este módulo adapta `feedback_utils.dart` de Flutter al portal RutX basado en Laravel, Blade, Vite y JavaScript nativo. Conserva la semántica visual y funcional: una sola notificación temporal activa, cuatro severidades, cierre manual, duraciones equivalentes y acción opcional de reintento.

> El código de dominio se mantiene en PHP; Blade monta un único contenedor global; JavaScript solo presenta el toast y comunica una intención de acción. De esta forma no se exponen tokens, excepciones ni closures del servidor al navegador.

| Flutter | Equivalente web | Duración | Resultado |
|---|---|---:|---|
| `showSuccess(context, mensaje)` | `Feedback::success($mensaje)` | 3 s | Toast verde de confirmación. |
| `showInfo(context, mensaje, actionLabel, onAction)` | `Feedback::info($mensaje, $actionLabel, $actionEvent)` | 3 s | Toast azul con acción opcional. |
| `showWarning(...)` | `Feedback::warning(...)` | 5 s por defecto | Toast naranja; admite duración entre 1 y 30 s. |
| `showError(context, error, onRetry)` | `Feedback::error($mensajeUsuario, $recuperable, $retryEvent)` | 5 s | Toast rojo; muestra `REINTENTAR` solo si procede. |
| `showErrorMessage(context, mensaje)` | `Feedback::errorMessage($mensaje)` | 5 s | Toast rojo sin acción. |

## Archivos del módulo

| Archivo | Responsabilidad |
|---|---|
| `app/Support/Feedback.php` | API PHP de construcción, validación y flash de una notificación. |
| `app/View/Components/FeedbackStack.php` | Consume el flash de sesión únicamente al montar el componente. |
| `resources/views/components/feedback-stack.blade.php` | Contenedor global, serialización segura y alternativa sin JavaScript. |
| `resources/js/feedback.js` | Renderizado accesible, reemplazo del toast activo, temporizador y evento de acción. |
| `resources/css/tokens.css` | Colores semánticos de información y fondos de feedback. |
| `resources/css/app.css` | Estilos, foco visible y respeto por `prefers-reduced-motion`. |
| `tests/Unit/Support/FeedbackTest.php` | Equivalencias de comportamiento y validación de eventos. |

## Uso desde un controlador

Los mensajes posteriores a un `redirect()` se guardan con `Feedback::flash()`. La llamada reemplaza el feedback pendiente anterior, de la misma manera en que Flutter ocultaba el `SnackBar` vigente antes de crear otro.

```php
use App\Support\Feedback;
use Illuminate\Http\RedirectResponse;

public function update(UpdateSaleRequest $request): RedirectResponse
{
    // El servicio remoto se invoca exclusivamente desde el servidor.
    $this->sales->update($request->validated());

    Feedback::flash(Feedback::success('La venta se actualizó correctamente.'));

    return to_route('sales.index');
}
```

Para un error recuperable, se envía un **nombre de evento validado**, no un callback PHP ni JavaScript serializado:

```php
Feedback::flash(Feedback::error(
    message: 'No fue posible consultar las ventas. Intenta nuevamente.',
    isRecoverable: true,
    retryEvent: 'sales.retry-load',
));
```

El backend debe seguir registrando el `trace_id` y mostrar al usuario solo un mensaje funcional. Ningún token, payload remoto, encabezado o excepción debe alimentar el texto de la notificación.

## Uso en una pantalla dinámica

El módulo expone `window.RutXFeedback` para la UI y escucha el evento de navegador `rutx:feedback`. Para mantener compatibilidad con la política API-first, el navegador no debe usar esta API para llamar al Sincronizador directamente.

```js
window.dispatchEvent(new CustomEvent('rutx:feedback', {
    detail: {
        type: 'warning',
        title: 'Sin conexión',
        message: 'La última actualización no pudo completarse.',
        duration: 5_000,
        action: null,
    },
}));
```

Cuando el usuario pulsa una acción, el módulo emite `rutx:feedback-action`. Cada pantalla puede escuchar únicamente los eventos que controla y delegar el trabajo a su componente del servidor. Con Livewire 3, la integración mínima es:

```js
window.addEventListener('rutx:feedback-action', ({ detail }) => {
    if (detail.event === 'sales.retry-load' && window.Livewire) {
        window.Livewire.dispatch('sales.retry-load', {
            feedbackId: detail.feedbackId,
        });
    }
});
```

> **Seguridad.** El evento de cliente expresa solamente intención. El manejador Livewire o el controlador debe revalidar autenticación, autorización, datos de entrada e idempotencia antes de repetir cualquier operación. En especial, no se deben reintentar automáticamente comandos de cancelación, asignación, aviso o traspaso.

## Comportamiento y accesibilidad

El contenedor se monta una sola vez en `layouts/app.blade.php`. El toast usa `role="alert"` para errores y `role="status"` en los demás casos, cuenta con botón de cierre, foco visible heredado del sistema y desactiva la animación para usuarios con reducción de movimiento. Los textos se insertan mediante `textContent`, por lo que el contenido remoto nunca se interpreta como HTML.

## Verificación realizada

| Comando | Resultado |
|---|---|
| `npm run build` | Compilación de Vite correcta. |
| `npm run check-tokens` | Auditoría anti-hex superada. |
| `php artisan test` | 6 pruebas aprobadas y 21 aserciones. |
| `vendor/bin/pint` | Formato PHP aplicado sin incidencias. |
| `git diff --check` | Sin errores de espacios finales ni de parches. |
