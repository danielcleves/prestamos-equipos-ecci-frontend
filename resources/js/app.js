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
