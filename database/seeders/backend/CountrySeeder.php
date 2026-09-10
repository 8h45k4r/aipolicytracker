<?php

namespace Database\Seeders\backend;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [

                "id" => Str::uuid(),
                "symbol" => "TV",
                "name" => "Tuvalu"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BV",
                "name" => "Bouvet Island"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GI",
                "name" => "Gibraltar"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GO",
                "name" => "Glorioso Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "JU",
                "name" => "Juan De Nova Island"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UM-DQ",
                "name" => "Jarvis Island"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UM-FQ",
                "name" => "Baker Island"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UM-HQ",
                "name" => "Howland Island"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UM-JQ",
                "name" => "Johnston Atoll"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UM-MQ",
                "name" => "Midway Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UM-WQ",
                "name" => "Wake Island"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BQ",
                "name" => "Bonair, Saint Eustachius and Saba"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NL",
                "name" => "Netherlands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ZW",
                "name" => "Zimbabwe"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ZM",
                "name" => "Zambia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ZA",
                "name" => "South Africa"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "YE",
                "name" => "Yemen"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "WS",
                "name" => "Samoa"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "WF",
                "name" => "Wallis and Futuna"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PS",
                "name" => "Palestinian Territories"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "VU",
                "name" => "Vanuatu"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "VN",
                "name" => "Vietnam"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "VI",
                "name" => "US Virgin Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "VG",
                "name" => "British Virgin Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "VE",
                "name" => "Venezuela"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "VC",
                "name" => "Saint Vincent and the Grenadines"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "VA",
                "name" => "Vatican City"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UZ",
                "name" => "Uzbekistan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "US",
                "name" => "United States"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UY",
                "name" => "Uruguay"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UA",
                "name" => "Ukraine"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "UG",
                "name" => "Uganda"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TZ",
                "name" => "Tanzania"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TW",
                "name" => "Taiwan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TR",
                "name" => "Türkiye"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TN",
                "name" => "Tunisia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TT",
                "name" => "Trinidad and Tobago"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TO",
                "name" => "Tonga"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TL",
                "name" => "Timor-Leste"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TM",
                "name" => "Turkmenistan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TK",
                "name" => "Tokelau"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TJ",
                "name" => "Tajikistan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TH",
                "name" => "Thailand"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TG",
                "name" => "Togo"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TD",
                "name" => "Chad"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TC",
                "name" => "Turks and Caicos Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SY",
                "name" => "Syria"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SC",
                "name" => "Seychelles"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SX",
                "name" => "Sint Maarten"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SZ",
                "name" => "Eswatini"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SE",
                "name" => "Sweden"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SI",
                "name" => "Slovenia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SK",
                "name" => "Slovakia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SR",
                "name" => "Suriname"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ST",
                "name" => "Sao Tome and Principe"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "RS",
                "name" => "Serbia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PM",
                "name" => "Saint Pierre and Miquelon"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SO",
                "name" => "Somalia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SM",
                "name" => "San Marino"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SV",
                "name" => "El Salvador"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SL",
                "name" => "Sierra Leone"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SB",
                "name" => "Solomon Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SH",
                "name" => "Saint Helena"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GS",
                "name" => "South Georgia and South Sandwich Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SG",
                "name" => "Singapore"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SN",
                "name" => "Senegal"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SS",
                "name" => "South Sudan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SD",
                "name" => "Sudan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SA",
                "name" => "Saudi Arabia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "EH",
                "name" => "Western Sahara"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "RW",
                "name" => "Rwanda"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "RU",
                "name" => "Russia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "RO",
                "name" => "Romania"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "RE",
                "name" => "Reunion"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "QA",
                "name" => "Qatar"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PF",
                "name" => "French Polynesia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PY",
                "name" => "Paraguay"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PT",
                "name" => "Portugal"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KP",
                "name" => "North Korea"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PR",
                "name" => "Puerto Rico"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PL",
                "name" => "Poland"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PG",
                "name" => "Papua New Guinea"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PW",
                "name" => "Palau"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PH",
                "name" => "Philippines"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PE",
                "name" => "Peru"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PN",
                "name" => "Pitcairn Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PA",
                "name" => "Panama"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "PK",
                "name" => "Pakistan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "OM",
                "name" => "Oman"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NZ",
                "name" => "New Zealand"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "SJ",
                "name" => "Svalbard and Jan Mayen"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NR",
                "name" => "Nauru"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NP",
                "name" => "Nepal"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NO",
                "name" => "Norway"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NU",
                "name" => "Niue"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NI",
                "name" => "Nicaragua"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NG",
                "name" => "Nigeria"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NF",
                "name" => "Norfolk Island"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NE",
                "name" => "Niger"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NC",
                "name" => "New Caledonia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "NA",
                "name" => "Namibia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "YT",
                "name" => "Mayotte"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MY",
                "name" => "Malaysia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MW",
                "name" => "Malawi"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MU",
                "name" => "Mauritius"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MQ",
                "name" => "Martinique"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MS",
                "name" => "Montserrat"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MR",
                "name" => "Mauritania"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MZ",
                "name" => "Mozambique"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MP",
                "name" => "Northern Mariana Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MN",
                "name" => "Mongolia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ME",
                "name" => "Montenegro"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MM",
                "name" => "Myanmar"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MT",
                "name" => "Malta"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ML",
                "name" => "Mali"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MK",
                "name" => "North Macedonia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MH",
                "name" => "Marshall Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MX",
                "name" => "Mexico"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MV",
                "name" => "Maldives"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MG",
                "name" => "Madagascar"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MD",
                "name" => "Moldova"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MC",
                "name" => "Monaco"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MA",
                "name" => "Morocco"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MF",
                "name" => "Saint Martin"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "MO",
                "name" => "Macau"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LV",
                "name" => "Latvia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LU",
                "name" => "Luxembourg"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LT",
                "name" => "Lithuania"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LS",
                "name" => "Lesotho"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LK",
                "name" => "Sri Lanka"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LI",
                "name" => "Liechtenstein"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LC",
                "name" => "Saint Lucia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LY",
                "name" => "Libya"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LR",
                "name" => "Liberia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LB",
                "name" => "Lebanon"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "LA",
                "name" => "Lao People's Democratic Republic"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KW",
                "name" => "Kuwait"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "XK",
                "name" => "Kosovo"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KR",
                "name" => "South Korea"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KN",
                "name" => "Saint Kitts and Nevis"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KI",
                "name" => "Kiribati"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KH",
                "name" => "Cambodia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KG",
                "name" => "Kyrgyzstan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KE",
                "name" => "Kenya"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KZ",
                "name" => "Kazakhstan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "JP",
                "name" => "Japan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "JO",
                "name" => "Jordan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "JE",
                "name" => "Jersey"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "JM",
                "name" => "Jamaica"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IT",
                "name" => "Italy"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IL",
                "name" => "Israel"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IS",
                "name" => "Iceland"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IQ",
                "name" => "Iraq"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IR",
                "name" => "Iran"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IE",
                "name" => "Ireland"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IO",
                "name" => "British Indian Ocean Territory"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IN",
                "name" => "India"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "IM",
                "name" => "Isle of Man"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ID",
                "name" => "Indonesia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "HU",
                "name" => "Hungary"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "HT",
                "name" => "Haiti"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "HR",
                "name" => "Croatia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "HN",
                "name" => "Honduras"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "HM",
                "name" => "Heard Island and McDonald Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "HK",
                "name" => "Hong Kong"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GY",
                "name" => "Guyana"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GU",
                "name" => "Guam"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GF",
                "name" => "French Guiana"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GT",
                "name" => "Guatemala"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GL",
                "name" => "Greenland"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GD",
                "name" => "Grenada"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GR",
                "name" => "Greece"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GQ",
                "name" => "Equatorial Guinea"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GW",
                "name" => "Guinea-Bissau"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GM",
                "name" => "Gambia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GP",
                "name" => "Guadeloupe"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GN",
                "name" => "Guinea"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GH",
                "name" => "Ghana"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GG",
                "name" => "Guernsey"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GE",
                "name" => "Georgia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GA",
                "name" => "Gabon"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "FR",
                "name" => "France"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "FM",
                "name" => "Federated States of Micronesia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "FO",
                "name" => "Faroe Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "FK",
                "name" => "Falkland Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "FJ",
                "name" => "Fiji"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "FI",
                "name" => "Finland"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ET",
                "name" => "Ethiopia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "EE",
                "name" => "Estonia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ES",
                "name" => "Spain"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "ER",
                "name" => "Eritrea"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "GB",
                "name" => "United Kingdom"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "EG",
                "name" => "Egypt"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "EC",
                "name" => "Ecuador"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "DZ",
                "name" => "Algeria"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "DO",
                "name" => "Dominican Republic"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "DK",
                "name" => "Denmark"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "DM",
                "name" => "Dominica"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "DJ",
                "name" => "Djibouti"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "DE",
                "name" => "Germany"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CZ",
                "name" => "Czechia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CY",
                "name" => "Cyprus"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KY",
                "name" => "Cayman Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CX",
                "name" => "Christmas Island"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CW",
                "name" => "Curaçao"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CU",
                "name" => "Cuba"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CR",
                "name" => "Costa Rica"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CV",
                "name" => "Cape Verde"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "KM",
                "name" => "Comoros"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CO",
                "name" => "Colombia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CK",
                "name" => "Cook Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CG",
                "name" => "Republic of Congo"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CD",
                "name" => "Democratic Republic of Congo"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CM",
                "name" => "Cameroon"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CI",
                "name" => "Côte d'Ivoire"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CN",
                "name" => "China"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CL",
                "name" => "Chile"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CH",
                "name" => "Switzerland"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CC",
                "name" => "Cocos (Keeling) Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CA",
                "name" => "Canada"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "CF",
                "name" => "Central African Republic"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BE",
                "name" => "Belgium"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BW",
                "name" => "Botswana"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BT",
                "name" => "Bhutan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BN",
                "name" => "Brunei"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BB",
                "name" => "Barbados"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BR",
                "name" => "Brazil"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BO",
                "name" => "Bolivia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BM",
                "name" => "Bermuda"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BZ",
                "name" => "Belize"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BY",
                "name" => "Belarus"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BL",
                "name" => "Saint Barthelemy"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BS",
                "name" => "Bahamas"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BH",
                "name" => "Bahrain"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BA",
                "name" => "Bosnia and Herzegovina"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BG",
                "name" => "Bulgaria"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BD",
                "name" => "Bangladesh"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BF",
                "name" => "Burkina Faso"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BJ",
                "name" => "Benin"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "BI",
                "name" => "Burundi"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AZ",
                "name" => "Azerbaijan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AT",
                "name" => "Austria"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AU",
                "name" => "Australia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "TF",
                "name" => "French Southern and Antarctic Lands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AQ",
                "name" => "Antarctica"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AS",
                "name" => "American Samoa"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AM",
                "name" => "Armenia"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AR",
                "name" => "Argentina"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AE",
                "name" => "United Arab Emirates"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AD",
                "name" => "Andorra"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AX",
                "name" => "Aland Islands"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AL",
                "name" => "Albania"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AI",
                "name" => "Anguilla"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AO",
                "name" => "Angola"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AF",
                "name" => "Afghanistan"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AG",
                "name" => "Antigua and Barbuda"
            ],
            [
                "id" => Str::uuid(),
                "symbol" => "AW",
                "name" => "Aruba"
            ]
        ];


        DB::table('countries')->insert($countries);
    }



    private function countryStore($countries = [])
    {
        foreach ($countries as $key => $value) {

        }
    }
}
