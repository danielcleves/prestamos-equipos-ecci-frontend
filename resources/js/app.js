//
// Modalidad del último gesto de entrada, para el halo de foco de 2 px
// (guía UI/UX §13; la regla vive en resources/css/app.css).
//
// :focus-visible no basta en los campos de texto: Chrome lo hace coincidir
// aunque se entre con el ratón, y el halo azul aparecería al hacer clic. Con
// `data-entrada="puntero"` en <html> el CSS apaga el halo tras un clic y lo
// recupera con la primera tecla.
const raiz = document.documentElement;

document.addEventListener(
    'pointerdown',
    () => raiz.setAttribute('data-entrada', 'puntero'),
    true
);

document.addEventListener(
    'keydown',
    () => raiz.removeAttribute('data-entrada'),
    true
);

//
// Devolver el foco al disparador de un modal al cerrarlo.
//
// Los modales de la app son diálogos de Alpine (role="dialog", Escape y «×»).
// Al abrirse el foco entra al diálogo —y el selector del disparador se pierde—
// así que al cerrarlos quedaba en <body> y el siguiente Tab reiniciaba desde
// «Saltar al contenido». La directiva x-foco-modal="<estado>" registra quién
// abrió el diálogo y le devuelve el foco al cerrarse; si el disparador ya no
// existe (p. ej. la fila desapareció tras una acción) el foco va al contenido
// principal, nunca se queda en <body>.
//
// Mientras el diálogo está abierto, la misma directiva cierra el ciclo de
// tabulación: Tab en la última parada vuelve a la primera y Shift+Tab en la
// primera salta a la última, para que el foco no se escape al menú lateral que
// queda detrás del velo.
//

const ID_CONTENIDO = 'contenido-principal';
const CLAVE_RETORNO = 'ecci_foco_al_volver';
const CONTROLES = 'button, a[href], input, select, textarea, [role="button"]';

// Elementos que reciben el foco al tabular dentro de un diálogo. `tabindex="-1"`
// queda fuera a propósito: es el foco programático (el propio diálogo), no una
// parada del recorrido.
const TABULABLES =
    'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';

// Elemento realmente enfocable: existe, sigue en el documento y se pinta.
function enfocable(elemento) {
    return (
        elemento instanceof Element &&
        elemento.isConnected &&
        elemento.getClientRects().length > 0
    );
}

// Paradas del recorrido del diálogo: las del selector, descartando lo apagado
// y lo oculto (display:none, [hidden] o un ancestro oculto).
function paradasDelDialogo(dialogo) {
    return Array.from(dialogo.querySelectorAll(TABULABLES)).filter(
        (elemento) =>
            !elemento.disabled &&
            !elemento.closest('[hidden]') &&
            enfocable(elemento)
    );
}

// Último control enfocado o pulsado fuera de un diálogo. Es la red de
// seguridad para Safari, donde pulsar un botón no le da el foco: en ese caso
// document.activeElement ya es <body> cuando el modal termina de abrirse.
let ultimoFocoExterno = null;

function recordarFocoExterno(elemento) {
    if (!enfocable(elemento) || elemento === document.body) return;
    if (elemento.closest('[role="dialog"]')) return;
    ultimoFocoExterno = elemento;
}

document.addEventListener(
    'focusin',
    (evento) => recordarFocoExterno(evento.target),
    true
);

document.addEventListener(
    'pointerdown',
    (evento) => {
        const control =
            evento.target instanceof Element
                ? evento.target.closest(CONTROLES)
                : null;

        if (control) recordarFocoExterno(control);
    },
    true
);

function enfocar(elemento) {
    if (!enfocable(elemento)) return false;
    elemento.focus({ preventScroll: true });

    return true;
}

// Quién abrió el diálogo: el foco actual si aún está fuera, o el último control
// externo que se pulsó o enfocó.
function disparadorDelDialogo(dialogo) {
    const activo = document.activeElement;

    if (enfocable(activo) && activo !== document.body && !dialogo.contains(activo)) {
        return activo;
    }

    return enfocable(ultimoFocoExterno) ? ultimoFocoExterno : null;
}

// Foco al cerrar: el disparador si sigue en la página; si no, el encabezado de
// contenido (<main id="contenido-principal" tabindex="-1">), que es el mismo
// destino de «Saltar al contenido».
function devolverFoco(dialogo, disparador) {
    if (enfocable(disparador) && !dialogo.contains(disparador) && enfocar(disparador)) {
        return;
    }

    enfocar(document.getElementById(ID_CONTENIDO));
}

// La directiva vive en el propio diálogo y recibe el estado de apertura, el
// mismo que ya usan x-show y x-effect en las vistas.
if (window.Alpine) {
    registrarDirectivaFoco();
} else {
    document.addEventListener('alpine:init', registrarDirectivaFoco);
}

function registrarDirectivaFoco() {
    window.Alpine.directive('foco-modal', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const estaAbierto = evaluateLater(expression);
        let estabaAbierto = false;
        let disparador = null;

        // Enviar un formulario del modal recarga la página y el disparador
        // puede haber desaparecido (una solicitud aprobada sale de la lista):
        // al volver, el foco va al contenido en lugar de quedarse en <body>.
        // En la microtarea ya se sabe si el formulario navegó o no (@submit.prevent).
        const alEnviar = (evento) => {
            queueMicrotask(() => {
                if (evento.defaultPrevented) return;

                try {
                    window.sessionStorage.setItem(CLAVE_RETORNO, '1');
                } catch (error) {
                    // sessionStorage bloqueado: se pierde el retorno, nada más.
                }
            });
        };

        el.addEventListener('submit', alEnviar, true);

        // Ciclo cerrado mientras el diálogo está abierto: Tab desde la última
        // parada vuelve a la primera y Shift+Tab desde la primera salta a la
        // última. Sin esto el foco salía al menú lateral, detrás del velo.
        // Con el diálogo cerrado no se toca nada.
        const alTeclear = (evento) => {
            if (evento.key !== 'Tab' || !estabaAbierto) return;

            const paradas = paradasDelDialogo(el);

            if (paradas.length === 0) {
                evento.preventDefault();

                return;
            }

            const primera = paradas[0];
            const ultima = paradas[paradas.length - 1];
            const activo = document.activeElement;

            // Foco en el propio contenedor (tabindex="-1") o fuera: la tecla
            // entra al diálogo por el extremo que corresponde.
            if (!el.contains(activo) || activo === el) {
                evento.preventDefault();
                (evento.shiftKey ? ultima : primera).focus();

                return;
            }

            if (!evento.shiftKey && activo === ultima) {
                evento.preventDefault();
                primera.focus();

                return;
            }

            if (evento.shiftKey && activo === primera) {
                evento.preventDefault();
                ultima.focus();
            }
        };

        el.addEventListener('keydown', alTeclear);

        cleanup(() => {
            el.removeEventListener('submit', alEnviar, true);
            el.removeEventListener('keydown', alTeclear);
        });

        effect(() =>
            estaAbierto((valor) => {
                const ahoraAbierto = Boolean(valor);

                if (ahoraAbierto === estabaAbierto) return;

                estabaAbierto = ahoraAbierto;

                if (ahoraAbierto) {
                    disparador = disparadorDelDialogo(el);

                    return;
                }

                // En una tarea aparte: si el usuario cierra el modal en el mismo
                // instante en que se abre, el foco de entrada ($nextTick de
                // x-effect) podría ejecutarse después y dejarlo en <body>.
                const alCerrar = disparador;

                disparador = null;
                setTimeout(() => devolverFoco(el, alCerrar), 0);
            })
        );
    });
}

// Cierre del ciclo tras una recarga provocada por un formulario del modal.
let volverAlContenido = false;

try {
    volverAlContenido = window.sessionStorage.getItem(CLAVE_RETORNO) === '1';

    if (volverAlContenido) window.sessionStorage.removeItem(CLAVE_RETORNO);
} catch (error) {
    volverAlContenido = false;
}

if (volverAlContenido) {
    document.addEventListener('DOMContentLoaded', () => {
        enfocar(document.getElementById(ID_CONTENIDO));
    });
}
