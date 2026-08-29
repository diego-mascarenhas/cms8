<?php

namespace App\Support;

final class MailerPresetCatalog
{
    public static function defaultCss(): string
    {
        return 'img{max-width:100%;height:auto;border-radius:8px;} h2{color:#1f2937;font-size:24px;margin:0 0 12px;} p{margin:0 0 14px;line-height:1.55;} a{color:#e85d4c;}';
    }

    /**
     * @return list<array{key: string, name: string, html: string, css: string}>
     */
    public static function templates(): array
    {
        return [
            self::preset(
                'newsletter',
                'Newsletter con foto',
                MailerStockImages::NEWSLETTER,
                'Equipo en la oficina',
                '<h2>Novedades para {{name}}</h2><p>Este mes resumimos lo más importante en un solo correo: producto, clientes y un próximo paso claro.</p><ul><li>Lo nuevo que ya podés usar</li><li>Una historia corta de un cliente</li><li>La fecha del próximo encuentro</li></ul><p><a href="#">Leer el newsletter</a></p>',
            ),
            self::preset(
                'welcome',
                'Bienvenida con imagen',
                MailerStockImages::WELCOME,
                'Equipo saludando',
                '<h2>Hola {{name}}, bienvenido</h2><p>Gracias por sumarte. En los próximos días te vamos a guiar para que saques provecho desde el primer envío.</p><p>Empezá por completar tu perfil y elegir a quién le vas a escribir.</p><p><a href="#">Empezar ahora</a></p>',
            ),
            self::preset(
                'promo',
                'Promoción con imagen',
                MailerStockImages::PROMO,
                'Oferta destacada',
                '<h2>{{name}}, hay una oferta para vos</h2><p>Durante esta semana reservamos un beneficio para quienes ya nos siguen.</p><p>Si te sirve, usalo antes de que venza. Si no, ignorá este correo: no hay compromiso.</p><p><a href="#">Ver la oferta</a></p>',
            ),
            self::preset(
                'event',
                'Invitación a evento',
                MailerStockImages::EVENT,
                'Público en un evento',
                '<h2>{{name}}, te invitamos</h2><p>Vamos a contar en vivo cómo armamos campañas que se leen y se responden.</p><p>Es una hora, con ejemplos reales y espacio para preguntas.</p><p><a href="#">Confirmar asistencia</a></p>',
            ),
            self::preset(
                'reminder',
                'Recordatorio con imagen',
                MailerStockImages::REMINDER,
                'Agenda y notas',
                '<h2>{{name}}, un recordatorio amable</h2><p>Queda pendiente un siguiente paso. Si ya lo resolviste, podés ignorar este mensaje.</p><p>Si no, te dejamos el enlace para retomarlo en un minuto.</p><p><a href="#">Retomar ahora</a></p>',
            ),
        ];
    }

    /**
     * @return list<array{name: string, text: string, html: string, css: string, profile: array{failed: int, sent_only: int, delivered: int, opened: int, clicked: int, unsent: int}}>
     */
    public static function news(): array
    {
        $css = self::defaultCss();

        return [
            [
                'name' => '[Demo] Newsletter de agosto',
                'text' => 'Tres novedades y un próximo paso para tu lista.',
                'html' => self::body(MailerStockImages::NEWSLETTER, 'Oficina', '<h2>Hola {{name}}, agosto en un vistazo</h2><p>Compartimos lo que más pidieron este mes: plantillas más claras, envíos pausados por defecto y un resumen de métricas.</p><ul><li>Nuevas plantillas con imagen</li><li>Mejor vista de entregas</li><li>Un webinar el jueves</li></ul><p><a href="#">Ver novedades</a></p>'),
                'css' => $css,
                'profile' => ['failed' => 2, 'sent_only' => 2, 'delivered' => 34, 'opened' => 18, 'clicked' => 7, 'unsent' => 2],
            ],
            [
                'name' => '[Demo] Bienvenida al club',
                'text' => 'Un primer correo cercano, con el siguiente paso.',
                'html' => self::body(MailerStockImages::WELCOME, 'Bienvenida', '<h2>Hola {{name}}, qué bueno que estés acá</h2><p>Este es el primer correo. No hay prisa: hoy alcanza con mirar tu perfil y elegir a quién le vas a escribir.</p><p><a href="#">Completar perfil</a></p>'),
                'css' => $css,
                'profile' => ['failed' => 1, 'sent_only' => 0, 'delivered' => 24, 'opened' => 14, 'clicked' => 4, 'unsent' => 3],
            ],
            [
                'name' => '[Demo] Promo de verano',
                'text' => 'Un beneficio de temporada, sin letra chica inventada.',
                'html' => self::body(MailerStockImages::PROMO, 'Promoción', '<h2>{{name}}, verano con un extra</h2><p>Esta semana hay un cupo reservado para quienes ya están en la lista. Si te sirve, usalo. Si no, este correo se puede ignorar.</p><p><a href="#">Ver beneficio</a></p>'),
                'css' => $css,
                'profile' => ['failed' => 1, 'sent_only' => 1, 'delivered' => 32, 'opened' => 20, 'clicked' => 12, 'unsent' => 6],
            ],
            [
                'name' => '[Demo] Invitación al webinar',
                'text' => 'Una hora en vivo, con ejemplos y preguntas.',
                'html' => self::body(MailerStockImages::EVENT, 'Webinar', '<h2>{{name}}, te esperamos el jueves</h2><p>Vamos a mostrar cómo se arma un News con imagen, audiencia y un llamado a la acción. Hay espacio para preguntas al final.</p><p><a href="#">Confirmar asistencia</a></p>'),
                'css' => $css,
                'profile' => ['failed' => 3, 'sent_only' => 1, 'delivered' => 18, 'opened' => 10, 'clicked' => 5, 'unsent' => 1],
            ],
            [
                'name' => '[Demo] Borrador sin envíos',
                'text' => 'Un News guardado, todavía sin destinatarios enviados.',
                'html' => self::body(MailerStockImages::REMINDER, 'Borrador', '<h2>Borrador para {{name}}</h2><p>Este mensaje está listo para editar. No tiene envíos: sirve para ver el estado vacío en la lista.</p><p><a href="#">Seguir editando</a></p>'),
                'css' => $css,
                'profile' => ['failed' => 0, 'sent_only' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0, 'unsent' => 0],
            ],
        ];
    }

    /**
     * @return list<array{name: string, surname: string, profile: string}>
     */
    public static function contacts(): array
    {
        return [
            ['name' => 'Lucía', 'surname' => 'García', 'profile' => 'Dueña de estudio'],
            ['name' => 'Martín', 'surname' => 'Pérez', 'profile' => 'Director comercial'],
            ['name' => 'Sofía', 'surname' => 'Rodríguez', 'profile' => 'Marketing'],
            ['name' => 'Diego', 'surname' => 'Fernández', 'profile' => 'Fundador'],
            ['name' => 'Valentina', 'surname' => 'López', 'profile' => 'Community'],
            ['name' => 'Nicolás', 'surname' => 'Martínez', 'profile' => 'Operaciones'],
            ['name' => 'Camila', 'surname' => 'Sánchez', 'profile' => 'Ventas'],
            ['name' => 'Joaquín', 'surname' => 'Gómez', 'profile' => 'Producto'],
            ['name' => 'Martina', 'surname' => 'Díaz', 'profile' => 'Atención'],
            ['name' => 'Facundo', 'surname' => 'Álvarez', 'profile' => 'CEO'],
            ['name' => 'Julieta', 'surname' => 'Romero', 'profile' => 'Diseño'],
            ['name' => 'Tomás', 'surname' => 'Torres', 'profile' => 'Desarrollo'],
            ['name' => 'Agustina', 'surname' => 'Ruiz', 'profile' => 'RRHH'],
            ['name' => 'Santiago', 'surname' => 'Ramírez', 'profile' => 'Finanzas'],
            ['name' => 'Florencia', 'surname' => 'Flores', 'profile' => 'Contenidos'],
            ['name' => 'Mateo', 'surname' => 'Acosta', 'profile' => 'Partner'],
            ['name' => 'Catalina', 'surname' => 'Benítez', 'profile' => 'Retail'],
            ['name' => 'Benjamín', 'surname' => 'Medina', 'profile' => 'Logística'],
            ['name' => 'Emilia', 'surname' => 'Castro', 'profile' => 'Consultora'],
            ['name' => 'Ignacio', 'surname' => 'Ortiz', 'profile' => 'Abogado'],
            ['name' => 'Paula', 'surname' => 'Silva', 'profile' => 'Arquitecta'],
            ['name' => 'Gonzalo', 'surname' => 'Vargas', 'profile' => 'Constructor'],
            ['name' => 'Micaela', 'surname' => 'Molina', 'profile' => 'Nutricionista'],
            ['name' => 'Andrés', 'surname' => 'Cabrera', 'profile' => 'Médico'],
            ['name' => 'Carolina', 'surname' => 'Ríos', 'profile' => 'Docente'],
            ['name' => 'Pablo', 'surname' => 'Herrera', 'profile' => 'Chef'],
            ['name' => 'Ailén', 'surname' => 'Aguirre', 'profile' => 'Fotógrafa'],
            ['name' => 'Leandro', 'surname' => 'Paz', 'profile' => 'Músico'],
            ['name' => 'Rocío', 'surname' => 'Navarro', 'profile' => 'Periodista'],
            ['name' => 'Federico', 'surname' => 'Sosa', 'profile' => 'Periodista'],
            ['name' => 'Malena', 'surname' => 'Iglesias', 'profile' => 'Editora'],
            ['name' => 'Ramiro', 'surname' => 'Vega', 'profile' => 'Analista'],
            ['name' => 'Bianca', 'surname' => 'Méndez', 'profile' => 'UX'],
            ['name' => 'Thiago', 'surname' => 'Cáceres', 'profile' => 'Soporte'],
            ['name' => 'Delfina', 'surname' => 'Bravo', 'profile' => 'Eventos'],
            ['name' => 'Lautaro', 'surname' => 'Peralta', 'profile' => 'Deportes'],
            ['name' => 'Renata', 'surname' => 'Miranda', 'profile' => 'Moda'],
            ['name' => 'Simón', 'surname' => 'Luna', 'profile' => 'Turismo'],
            ['name' => 'Olivia', 'surname' => 'Campos', 'profile' => 'ONG'],
            ['name' => 'Bautista', 'surname' => 'Reyes', 'profile' => 'Inversor'],
        ];
    }

    /**
     * @return array{key: string, name: string, html: string, css: string}
     */
    private static function preset(string $key, string $name, string $src, string $alt, string $body): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'html' => self::body($src, $alt, $body),
            'css' => self::defaultCss(),
        ];
    }

    private static function body(string $src, string $alt, string $inner): string
    {
        return MailerStockImages::heroHtml($src, $alt).$inner;
    }
}
