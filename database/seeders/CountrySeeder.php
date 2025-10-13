<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Complete list of all countries according to ISO 3166-1 standard
     * Source: ISO 3166-1 official codes (2024)
     */
    public function run()
    {
        $countries = [
            // A
            ['id' => 4,   'name' => 'Afganistán', 'code' => 'AF'],
            ['id' => 248, 'name' => 'Islas Åland', 'code' => 'AX'],
            ['id' => 8,   'name' => 'Albania', 'code' => 'AL'],
            ['id' => 12,  'name' => 'Argelia', 'code' => 'DZ'],
            ['id' => 16,  'name' => 'Samoa Americana', 'code' => 'AS'],
            ['id' => 20,  'name' => 'Andorra', 'code' => 'AD'],
            ['id' => 24,  'name' => 'Angola', 'code' => 'AO'],
            ['id' => 660, 'name' => 'Anguila', 'code' => 'AI'],
            ['id' => 10,  'name' => 'Antártida', 'code' => 'AQ'],
            ['id' => 28,  'name' => 'Antigua y Barbuda', 'code' => 'AG'],
            ['id' => 32,  'name' => 'Argentina', 'code' => 'AR'],
            ['id' => 51,  'name' => 'Armenia', 'code' => 'AM'],
            ['id' => 533, 'name' => 'Aruba', 'code' => 'AW'],
            ['id' => 36,  'name' => 'Australia', 'code' => 'AU'],
            ['id' => 40,  'name' => 'Austria', 'code' => 'AT'],
            ['id' => 31,  'name' => 'Azerbaiyán', 'code' => 'AZ'],

            // B
            ['id' => 44,  'name' => 'Bahamas', 'code' => 'BS'],
            ['id' => 48,  'name' => 'Baréin', 'code' => 'BH'],
            ['id' => 50,  'name' => 'Bangladés', 'code' => 'BD'],
            ['id' => 52,  'name' => 'Barbados', 'code' => 'BB'],
            ['id' => 112, 'name' => 'Bielorrusia', 'code' => 'BY'],
            ['id' => 56,  'name' => 'Bélgica', 'code' => 'BE'],
            ['id' => 84,  'name' => 'Belice', 'code' => 'BZ'],
            ['id' => 204, 'name' => 'Benín', 'code' => 'BJ'],
            ['id' => 60,  'name' => 'Bermudas', 'code' => 'BM'],
            ['id' => 64,  'name' => 'Bután', 'code' => 'BT'],
            ['id' => 68,  'name' => 'Bolivia', 'code' => 'BO'],
            ['id' => 535, 'name' => 'Bonaire, San Eustaquio y Saba', 'code' => 'BQ'],
            ['id' => 70,  'name' => 'Bosnia y Herzegovina', 'code' => 'BA'],
            ['id' => 72,  'name' => 'Botsuana', 'code' => 'BW'],
            ['id' => 74,  'name' => 'Isla Bouvet', 'code' => 'BV'],
            ['id' => 76,  'name' => 'Brasil', 'code' => 'BR'],
            ['id' => 86,  'name' => 'Territorio Británico del Océano Índico', 'code' => 'IO'],
            ['id' => 96,  'name' => 'Brunéi', 'code' => 'BN'],
            ['id' => 100, 'name' => 'Bulgaria', 'code' => 'BG'],
            ['id' => 854, 'name' => 'Burkina Faso', 'code' => 'BF'],
            ['id' => 108, 'name' => 'Burundi', 'code' => 'BI'],

            // C
            ['id' => 132, 'name' => 'Cabo Verde', 'code' => 'CV'],
            ['id' => 116, 'name' => 'Camboya', 'code' => 'KH'],
            ['id' => 120, 'name' => 'Camerún', 'code' => 'CM'],
            ['id' => 124, 'name' => 'Canadá', 'code' => 'CA'],
            ['id' => 136, 'name' => 'Islas Caimán', 'code' => 'KY'],
            ['id' => 140, 'name' => 'República Centroafricana', 'code' => 'CF'],
            ['id' => 148, 'name' => 'Chad', 'code' => 'TD'],
            ['id' => 152, 'name' => 'Chile', 'code' => 'CL'],
            ['id' => 156, 'name' => 'China', 'code' => 'CN'],
            ['id' => 162, 'name' => 'Isla de Navidad', 'code' => 'CX'],
            ['id' => 166, 'name' => 'Islas Cocos', 'code' => 'CC'],
            ['id' => 170, 'name' => 'Colombia', 'code' => 'CO'],
            ['id' => 174, 'name' => 'Comoras', 'code' => 'KM'],
            ['id' => 178, 'name' => 'Congo', 'code' => 'CG'],
            ['id' => 180, 'name' => 'República Democrática del Congo', 'code' => 'CD'],
            ['id' => 184, 'name' => 'Islas Cook', 'code' => 'CK'],
            ['id' => 188, 'name' => 'Costa Rica', 'code' => 'CR'],
            ['id' => 384, 'name' => 'Costa de Marfil', 'code' => 'CI'],
            ['id' => 191, 'name' => 'Croacia', 'code' => 'HR'],
            ['id' => 192, 'name' => 'Cuba', 'code' => 'CU'],
            ['id' => 531, 'name' => 'Curazao', 'code' => 'CW'],
            ['id' => 196, 'name' => 'Chipre', 'code' => 'CY'],
            ['id' => 203, 'name' => 'República Checa', 'code' => 'CZ'],

            // D
            ['id' => 208, 'name' => 'Dinamarca', 'code' => 'DK'],
            ['id' => 262, 'name' => 'Yibuti', 'code' => 'DJ'],
            ['id' => 212, 'name' => 'Dominica', 'code' => 'DM'],
            ['id' => 214, 'name' => 'República Dominicana', 'code' => 'DO'],

            // E
            ['id' => 218, 'name' => 'Ecuador', 'code' => 'EC'],
            ['id' => 818, 'name' => 'Egipto', 'code' => 'EG'],
            ['id' => 222, 'name' => 'El Salvador', 'code' => 'SV'],
            ['id' => 226, 'name' => 'Guinea Ecuatorial', 'code' => 'GQ'],
            ['id' => 232, 'name' => 'Eritrea', 'code' => 'ER'],
            ['id' => 233, 'name' => 'Estonia', 'code' => 'EE'],
            ['id' => 748, 'name' => 'Esuatini', 'code' => 'SZ'],
            ['id' => 231, 'name' => 'Etiopía', 'code' => 'ET'],

            // F
            ['id' => 238, 'name' => 'Islas Malvinas', 'code' => 'FK'],
            ['id' => 234, 'name' => 'Islas Feroe', 'code' => 'FO'],
            ['id' => 242, 'name' => 'Fiyi', 'code' => 'FJ'],
            ['id' => 246, 'name' => 'Finlandia', 'code' => 'FI'],
            ['id' => 250, 'name' => 'Francia', 'code' => 'FR'],
            ['id' => 254, 'name' => 'Guayana Francesa', 'code' => 'GF'],
            ['id' => 258, 'name' => 'Polinesia Francesa', 'code' => 'PF'],
            ['id' => 260, 'name' => 'Territorios Australes Franceses', 'code' => 'TF'],

            // G
            ['id' => 266, 'name' => 'Gabón', 'code' => 'GA'],
            ['id' => 270, 'name' => 'Gambia', 'code' => 'GM'],
            ['id' => 268, 'name' => 'Georgia', 'code' => 'GE'],
            ['id' => 276, 'name' => 'Alemania', 'code' => 'DE'],
            ['id' => 288, 'name' => 'Ghana', 'code' => 'GH'],
            ['id' => 292, 'name' => 'Gibraltar', 'code' => 'GI'],
            ['id' => 300, 'name' => 'Grecia', 'code' => 'GR'],
            ['id' => 304, 'name' => 'Groenlandia', 'code' => 'GL'],
            ['id' => 308, 'name' => 'Granada', 'code' => 'GD'],
            ['id' => 312, 'name' => 'Guadalupe', 'code' => 'GP'],
            ['id' => 316, 'name' => 'Guam', 'code' => 'GU'],
            ['id' => 320, 'name' => 'Guatemala', 'code' => 'GT'],
            ['id' => 831, 'name' => 'Guernsey', 'code' => 'GG'],
            ['id' => 324, 'name' => 'Guinea', 'code' => 'GN'],
            ['id' => 624, 'name' => 'Guinea-Bisáu', 'code' => 'GW'],
            ['id' => 328, 'name' => 'Guyana', 'code' => 'GY'],

            // H
            ['id' => 332, 'name' => 'Haití', 'code' => 'HT'],
            ['id' => 334, 'name' => 'Islas Heard y McDonald', 'code' => 'HM'],
            ['id' => 336, 'name' => 'Santa Sede', 'code' => 'VA'],
            ['id' => 340, 'name' => 'Honduras', 'code' => 'HN'],
            ['id' => 344, 'name' => 'Hong Kong', 'code' => 'HK'],
            ['id' => 348, 'name' => 'Hungría', 'code' => 'HU'],

            // I
            ['id' => 352, 'name' => 'Islandia', 'code' => 'IS'],
            ['id' => 356, 'name' => 'India', 'code' => 'IN'],
            ['id' => 360, 'name' => 'Indonesia', 'code' => 'ID'],
            ['id' => 364, 'name' => 'Irán', 'code' => 'IR'],
            ['id' => 368, 'name' => 'Irak', 'code' => 'IQ'],
            ['id' => 372, 'name' => 'Irlanda', 'code' => 'IE'],
            ['id' => 833, 'name' => 'Isla de Man', 'code' => 'IM'],
            ['id' => 376, 'name' => 'Israel', 'code' => 'IL'],
            ['id' => 380, 'name' => 'Italia', 'code' => 'IT'],

            // J
            ['id' => 388, 'name' => 'Jamaica', 'code' => 'JM'],
            ['id' => 392, 'name' => 'Japón', 'code' => 'JP'],
            ['id' => 832, 'name' => 'Jersey', 'code' => 'JE'],
            ['id' => 400, 'name' => 'Jordania', 'code' => 'JO'],

            // K
            ['id' => 398, 'name' => 'Kazajstán', 'code' => 'KZ'],
            ['id' => 404, 'name' => 'Kenia', 'code' => 'KE'],
            ['id' => 296, 'name' => 'Kiribati', 'code' => 'KI'],
            ['id' => 408, 'name' => 'Corea del Norte', 'code' => 'KP'],
            ['id' => 410, 'name' => 'Corea del Sur', 'code' => 'KR'],
            ['id' => 414, 'name' => 'Kuwait', 'code' => 'KW'],
            ['id' => 417, 'name' => 'Kirguistán', 'code' => 'KG'],

            // L
            ['id' => 418, 'name' => 'Laos', 'code' => 'LA'],
            ['id' => 428, 'name' => 'Letonia', 'code' => 'LV'],
            ['id' => 422, 'name' => 'Líbano', 'code' => 'LB'],
            ['id' => 426, 'name' => 'Lesoto', 'code' => 'LS'],
            ['id' => 430, 'name' => 'Liberia', 'code' => 'LR'],
            ['id' => 434, 'name' => 'Libia', 'code' => 'LY'],
            ['id' => 438, 'name' => 'Liechtenstein', 'code' => 'LI'],
            ['id' => 440, 'name' => 'Lituania', 'code' => 'LT'],
            ['id' => 442, 'name' => 'Luxemburgo', 'code' => 'LU'],

            // M
            ['id' => 446, 'name' => 'Macao', 'code' => 'MO'],
            ['id' => 450, 'name' => 'Madagascar', 'code' => 'MG'],
            ['id' => 454, 'name' => 'Malaui', 'code' => 'MW'],
            ['id' => 458, 'name' => 'Malasia', 'code' => 'MY'],
            ['id' => 462, 'name' => 'Maldivas', 'code' => 'MV'],
            ['id' => 466, 'name' => 'Malí', 'code' => 'ML'],
            ['id' => 470, 'name' => 'Malta', 'code' => 'MT'],
            ['id' => 584, 'name' => 'Islas Marshall', 'code' => 'MH'],
            ['id' => 474, 'name' => 'Martinica', 'code' => 'MQ'],
            ['id' => 478, 'name' => 'Mauritania', 'code' => 'MR'],
            ['id' => 480, 'name' => 'Mauricio', 'code' => 'MU'],
            ['id' => 175, 'name' => 'Mayotte', 'code' => 'YT'],
            ['id' => 484, 'name' => 'México', 'code' => 'MX'],
            ['id' => 583, 'name' => 'Micronesia', 'code' => 'FM'],
            ['id' => 498, 'name' => 'Moldavia', 'code' => 'MD'],
            ['id' => 492, 'name' => 'Mónaco', 'code' => 'MC'],
            ['id' => 496, 'name' => 'Mongolia', 'code' => 'MN'],
            ['id' => 499, 'name' => 'Montenegro', 'code' => 'ME'],
            ['id' => 500, 'name' => 'Montserrat', 'code' => 'MS'],
            ['id' => 504, 'name' => 'Marruecos', 'code' => 'MA'],
            ['id' => 508, 'name' => 'Mozambique', 'code' => 'MZ'],
            ['id' => 104, 'name' => 'Birmania', 'code' => 'MM'],

            // N
            ['id' => 516, 'name' => 'Namibia', 'code' => 'NA'],
            ['id' => 520, 'name' => 'Nauru', 'code' => 'NR'],
            ['id' => 524, 'name' => 'Nepal', 'code' => 'NP'],
            ['id' => 528, 'name' => 'Países Bajos', 'code' => 'NL'],
            ['id' => 540, 'name' => 'Nueva Caledonia', 'code' => 'NC'],
            ['id' => 554, 'name' => 'Nueva Zelanda', 'code' => 'NZ'],
            ['id' => 558, 'name' => 'Nicaragua', 'code' => 'NI'],
            ['id' => 562, 'name' => 'Níger', 'code' => 'NE'],
            ['id' => 566, 'name' => 'Nigeria', 'code' => 'NG'],
            ['id' => 570, 'name' => 'Niue', 'code' => 'NU'],
            ['id' => 574, 'name' => 'Isla Norfolk', 'code' => 'NF'],
            ['id' => 807, 'name' => 'Macedonia del Norte', 'code' => 'MK'],
            ['id' => 580, 'name' => 'Islas Marianas del Norte', 'code' => 'MP'],
            ['id' => 578, 'name' => 'Noruega', 'code' => 'NO'],

            // O
            ['id' => 512, 'name' => 'Omán', 'code' => 'OM'],

            // P
            ['id' => 586, 'name' => 'Pakistán', 'code' => 'PK'],
            ['id' => 585, 'name' => 'Palaos', 'code' => 'PW'],
            ['id' => 275, 'name' => 'Palestina', 'code' => 'PS'],
            ['id' => 591, 'name' => 'Panamá', 'code' => 'PA'],
            ['id' => 598, 'name' => 'Papúa Nueva Guinea', 'code' => 'PG'],
            ['id' => 600, 'name' => 'Paraguay', 'code' => 'PY'],
            ['id' => 604, 'name' => 'Perú', 'code' => 'PE'],
            ['id' => 608, 'name' => 'Filipinas', 'code' => 'PH'],
            ['id' => 612, 'name' => 'Islas Pitcairn', 'code' => 'PN'],
            ['id' => 616, 'name' => 'Polonia', 'code' => 'PL'],
            ['id' => 620, 'name' => 'Portugal', 'code' => 'PT'],
            ['id' => 630, 'name' => 'Puerto Rico', 'code' => 'PR'],

            // Q
            ['id' => 634, 'name' => 'Catar', 'code' => 'QA'],

            // R
            ['id' => 638, 'name' => 'Reunión', 'code' => 'RE'],
            ['id' => 642, 'name' => 'Rumanía', 'code' => 'RO'],
            ['id' => 643, 'name' => 'Rusia', 'code' => 'RU'],
            ['id' => 646, 'name' => 'Ruanda', 'code' => 'RW'],

            // S
            ['id' => 652, 'name' => 'San Bartolomé', 'code' => 'BL'],
            ['id' => 654, 'name' => 'Santa Elena', 'code' => 'SH'],
            ['id' => 659, 'name' => 'San Cristóbal y Nieves', 'code' => 'KN'],
            ['id' => 662, 'name' => 'Santa Lucía', 'code' => 'LC'],
            ['id' => 663, 'name' => 'San Martín', 'code' => 'MF'],
            ['id' => 666, 'name' => 'San Pedro y Miquelón', 'code' => 'PM'],
            ['id' => 670, 'name' => 'San Vicente y las Granadinas', 'code' => 'VC'],
            ['id' => 882, 'name' => 'Samoa', 'code' => 'WS'],
            ['id' => 674, 'name' => 'San Marino', 'code' => 'SM'],
            ['id' => 678, 'name' => 'Santo Tomé y Príncipe', 'code' => 'ST'],
            ['id' => 682, 'name' => 'Arabia Saudí', 'code' => 'SA'],
            ['id' => 686, 'name' => 'Senegal', 'code' => 'SN'],
            ['id' => 688, 'name' => 'Serbia', 'code' => 'RS'],
            ['id' => 690, 'name' => 'Seychelles', 'code' => 'SC'],
            ['id' => 694, 'name' => 'Sierra Leona', 'code' => 'SL'],
            ['id' => 702, 'name' => 'Singapur', 'code' => 'SG'],
            ['id' => 534, 'name' => 'Sint Maarten', 'code' => 'SX'],
            ['id' => 703, 'name' => 'Eslovaquia', 'code' => 'SK'],
            ['id' => 705, 'name' => 'Eslovenia', 'code' => 'SI'],
            ['id' => 90,  'name' => 'Islas Salomón', 'code' => 'SB'],
            ['id' => 706, 'name' => 'Somalia', 'code' => 'SO'],
            ['id' => 710, 'name' => 'Sudáfrica', 'code' => 'ZA'],
            ['id' => 239, 'name' => 'Islas Georgias del Sur y Sandwich del Sur', 'code' => 'GS'],
            ['id' => 728, 'name' => 'Sudán del Sur', 'code' => 'SS'],
            ['id' => 724, 'name' => 'España', 'code' => 'ES'],
            ['id' => 144, 'name' => 'Sri Lanka', 'code' => 'LK'],
            ['id' => 729, 'name' => 'Sudán', 'code' => 'SD'],
            ['id' => 740, 'name' => 'Surinam', 'code' => 'SR'],
            ['id' => 744, 'name' => 'Svalbard y Jan Mayen', 'code' => 'SJ'],
            ['id' => 752, 'name' => 'Suecia', 'code' => 'SE'],
            ['id' => 756, 'name' => 'Suiza', 'code' => 'CH'],
            ['id' => 760, 'name' => 'Siria', 'code' => 'SY'],

            // T
            ['id' => 158, 'name' => 'Taiwán', 'code' => 'TW'],
            ['id' => 762, 'name' => 'Tayikistán', 'code' => 'TJ'],
            ['id' => 834, 'name' => 'Tanzania', 'code' => 'TZ'],
            ['id' => 764, 'name' => 'Tailandia', 'code' => 'TH'],
            ['id' => 626, 'name' => 'Timor Oriental', 'code' => 'TL'],
            ['id' => 768, 'name' => 'Togo', 'code' => 'TG'],
            ['id' => 772, 'name' => 'Tokelau', 'code' => 'TK'],
            ['id' => 776, 'name' => 'Tonga', 'code' => 'TO'],
            ['id' => 780, 'name' => 'Trinidad y Tobago', 'code' => 'TT'],
            ['id' => 788, 'name' => 'Túnez', 'code' => 'TN'],
            ['id' => 792, 'name' => 'Turquía', 'code' => 'TR'],
            ['id' => 795, 'name' => 'Turkmenistán', 'code' => 'TM'],
            ['id' => 796, 'name' => 'Islas Turcas y Caicos', 'code' => 'TC'],
            ['id' => 798, 'name' => 'Tuvalu', 'code' => 'TV'],

            // U
            ['id' => 800, 'name' => 'Uganda', 'code' => 'UG'],
            ['id' => 804, 'name' => 'Ucrania', 'code' => 'UA'],
            ['id' => 784, 'name' => 'Emiratos Árabes Unidos', 'code' => 'AE'],
            ['id' => 826, 'name' => 'Reino Unido', 'code' => 'GB'],
            ['id' => 581, 'name' => 'Islas Ultramarinas Menores de Estados Unidos', 'code' => 'UM'],
            ['id' => 840, 'name' => 'Estados Unidos', 'code' => 'US'],
            ['id' => 858, 'name' => 'Uruguay', 'code' => 'UY'],
            ['id' => 860, 'name' => 'Uzbekistán', 'code' => 'UZ'],

            // V
            ['id' => 548, 'name' => 'Vanuatu', 'code' => 'VU'],
            ['id' => 862, 'name' => 'Venezuela', 'code' => 'VE'],
            ['id' => 704, 'name' => 'Vietnam', 'code' => 'VN'],
            ['id' => 92,  'name' => 'Islas Vírgenes Británicas', 'code' => 'VG'],
            ['id' => 850, 'name' => 'Islas Vírgenes de Estados Unidos', 'code' => 'VI'],

            // W
            ['id' => 876, 'name' => 'Wallis y Futuna', 'code' => 'WF'],
            ['id' => 732, 'name' => 'Sáhara Occidental', 'code' => 'EH'],

            // Y
            ['id' => 887, 'name' => 'Yemen', 'code' => 'YE'],

            // Z
            ['id' => 894, 'name' => 'Zambia', 'code' => 'ZM'],
            ['id' => 716, 'name' => 'Zimbabue', 'code' => 'ZW'],
        ];

        foreach ($countries as $country)
        {
            Country::updateOrCreate(
                ['id' => $country['id']],
                $country,
            );
        }
    }
}
