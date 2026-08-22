{{--
    <x-scroll-area tag="div" tone="light" class="h-64">
    Contenedor desplazable reutilizable con scrollbar propio estilizado.

    Responsabilidades:
      - Concentrar el comportamiento de scroll interno del portal
        (overflow-y auto + overflow-x hidden) en un único componente.
      - Estilizar el scrollbar de forma discreta y consistente con los
        tokens RutX (clases .rutx-scroll en resources/css/app.css).
      - Preservar la semántica del elemento anfitrión vía la prop `tag`
        (ej. <nav> para el sidebar).

    Props:
      - tone: 'light' → fondo claro, thumb gris | 'dark' → fondo oscuro
        (sidebar), thumb blanco translúcido. Default: 'light'.
      - tag:  elemento HTML a renderizar. Default: 'div'.

    El alto o crecimiento del contenedor se pasa por atributos, ej.
    class="flex-1" dentro de un shell acotado o class="h-64" fijo.
--}}

@props([
    'tone' => 'light',
    'tag' => 'div',
])

<{{ $tag }} {{ $attributes->merge(['class' => 'rutx-scroll rutx-scroll--'.$tone]) }}>
    {{ $slot }}
</{{ $tag }}>
