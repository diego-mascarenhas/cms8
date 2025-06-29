<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition()
    {
        $users = User::all();

        // Define language distribution - weighted to have more Spanish speakers
        $languages = [
            'es' => 70,  // 70% Spanish
            'en' => 12,  // 12% English
            'fr' => 8,   // 8% French
            'de' => 5,   // 5% German
            'it' => 3,   // 3% Italian
            'pt' => 2,   // 2% Portuguese
        ];

        // Create weighted array for random selection
        $weightedLanguages = [];
        foreach ($languages as $lang => $weight) {
            $weightedLanguages = array_merge($weightedLanguages, array_fill(0, $weight, $lang));
        }

        return [
            'team_id' => 1,
            'name' => $this->faker->company,
            'language' => $this->faker->randomElement($weightedLanguages),
            'country' => 724, // Spain - matches the default value in migration
            'creator_id' => $this->faker->boolean(70) ? $users->random()->id : $this->faker->randomElement($users)->id,
            'responsible_id' => $this->faker->boolean(70) ? $users->random()->id : $this->faker->randomElement($users)->id,
            'status_id' => ContactStatus::inRandomOrder()->first()->id,
            // 'source_id' => $this->faker->numberBetween(1, 3),
            'birthday' => $this->faker->date(),
            'profile' => $this->generateSpanishProfile(),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Contact $contact) {
            $sources = Source::where('id', '<=', 3)->inRandomOrder()->get();

            if ($primarySource = $sources->first()) {
                $contact->source_id = $primarySource->id;
                $contact->save();
            }
        });
    }

    /**
     * Generate a realistic Spanish profile for collaborators
     */
    private function generateSpanishProfile()
    {
        $profiles = [
            'Traductora freelance especializada en documentación técnica y marketing digital. Con más de 5 años de experiencia trabajando con empresas internacionales.',
            'Intérprete profesional con experiencia en conferencias empresariales y eventos corporativos. Especializada en sector financiero y tecnológico.',
            'Licenciada en Filología con especialización en traducción audiovisual. Experta en subtitulado y doblaje para plataformas streaming.',
            'Profesional de la traducción médica con certificación internacional. Experiencia en documentación farmacéutica y estudios clínicos.',
            'Traductora jurada con amplia experiencia en documentación legal y judicial. Colaboradora habitual de despachos de abogados internacionales.',
            'Especialista en localización de software y aplicaciones móviles. Experiencia trabajando con startups tecnológicas y empresas de videojuegos.',
            'Experta en traducción comercial e institucional. Colabora regularmente con organismos públicos y entidades financieras.',
            'Traductora especializada en contenido web y marketing digital. Experiencia en SEO multiidioma y gestión de redes sociales.',
            'Profesional de la interpretación consecutiva y simultánea. Especializada en sector industrial y energético renovables.',
            'Licenciada en Traducción e Interpretación con máster en comunicación intercultural. Especialista en relaciones internacionales.',
            'Traductora freelance con experiencia en edición y corrección de textos. Colabora con editoriales y medios de comunicación.',
            'Experta en traducción científica y académica. Doctora en Lingüística Aplicada con publicaciones internacionales.',
            'Especialista en traducción turística y gastronómica. Colabora con agencias de viajes y cadenas hoteleras internacionales.',
            'Traductora técnica especializada en ingeniería y arquitectura. Experiencia en proyectos de construcción e infraestructuras.',
            'Profesional de la traducción financiera con certificación en mercados internacionales. Experiencia en banca y seguros.',
        ];

        return $this->faker->randomElement($profiles);
    }
}
