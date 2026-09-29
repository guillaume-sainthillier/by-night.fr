<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925182202 extends AbstractMigration
{
    /**
     * Country code => the headline and introduction (Markdown) of its portal. No hero caption: it is printed over the
     * hero image, and until one is uploaded the portals show the generic landing photo.
     */
    private const array COUNTRIES = [
        'FR' => [
            'headline' => 'Concerts, festivals, expos & marchés',
            'description' => "Des salles parisiennes aux guinguettes de la Garonne, des festivals bretons aux marchés de Provence\u{a0}: concerts, spectacles, expositions, ateliers et soirées dans toutes les villes de France, mis à jour chaque jour.",
        ],
        'MC' => [
            'headline' => 'Concerts, galas & grands rendez-vous',
            'description' => "L'agenda des sorties sur le Rocher et le littoral monégasque\u{a0}: opéra et concerts à Monte-Carlo, expositions du Nouveau Musée National, marché de la Condamine et grands rendez-vous de la Principauté.",
        ],
        'CH' => [
            'headline' => 'Concerts, festivals & sorties romandes',
            'description' => 'De Genève aux rives du Léman, les concerts, spectacles, expositions et festivals de Suisse romande, mis à jour chaque jour.',
        ],
        'BE' => [
            'headline' => 'Concerts, expos & festivals',
            'description' => 'De Bruxelles à Liège, les concerts, spectacles, expositions et festivals de Belgique, mis à jour chaque jour.',
        ],
        'RE' => [
            'headline' => 'Maloya, festivals & sorties créoles',
            'description' => "De Saint-Denis à Saint-Paul, des scènes de maloya aux festivals en plein air\u{a0}: concerts, spectacles, expositions et sorties en famille sur l'île de La Réunion.",
        ],
        'MQ' => [
            'headline' => 'Bèlè, carnaval & fêtes patronales',
            'description' => "De Fort-de-France à Saint-Pierre, des soirées bèlè aux fêtes patronales\u{a0}: concerts, spectacles, expositions et sorties en famille en Martinique.",
        ],
        'GP' => [
            'headline' => 'Gwoka, carnaval & sorties créoles',
            'description' => "De Pointe-à-Pitre à Basse-Terre, des soirées léwoz au carnaval\u{a0}: concerts, spectacles, expositions et sorties en famille en Guadeloupe.",
        ],
        'GF' => [
            'headline' => 'Carnaval, musiques & sorties guyanaises',
            'description' => "De Cayenne à Saint-Laurent-du-Maroni, des nuits du carnaval aux scènes de musique live\u{a0}: concerts, spectacles, expositions et sorties en famille en Guyane.",
        ],
        'YT' => [
            'headline' => 'Musiques, traditions & sorties mahoraises',
            'description' => 'De Mamoudzou à Dzaoudzi, les concerts, spectacles, fêtes traditionnelles et sorties en famille à Mayotte.',
        ],
    ];

    /** The country of the big card of the home page */
    private const string FEATURED_COUNTRY = 'FR';

    /**
     * GeoNames id => the headline (also the sub-title of its card on the home page) and introduction (Markdown) of
     * the 22 cities with the legal status of métropole, by the ids of the real cities: until
     * app:places:fix-namesake-cities runs, Toulon and Saint-Étienne have their events on a namesake hamlet.
     */
    private const array METROPOLISES = [
        // Paris
        2988507 => [
            'headline' => 'De Montmartre aux quais de Seine',
            'description' => "Des grandes salles aux caves de jazz, des musées aux théâtres de quartier\u{a0}: concerts, spectacles, expositions, soirées et sorties en famille dans les vingt arrondissements de Paris.",
        ],
        // Marseille
        2995469 => [
            'headline' => 'Du Vieux-Port aux calanques',
            'description' => "Du Vieux-Port au Mucem, du Dôme au cours Julien\u{a0}: concerts, spectacles, expositions, festivals et soirées dans la cité phocéenne.",
        ],
        // Lyon
        2996944 => [
            'headline' => 'Du Vieux-Lyon aux quais de Saône',
            'description' => "Des traboules du Vieux-Lyon aux pentes de la Croix-Rousse, de la Confluence aux Nuits de Fourvière\u{a0}: concerts, spectacles, expositions et soirées entre Rhône et Saône.",
        ],
        // Toulouse
        2972315 => [
            'headline' => 'Du Capitole aux quais de la Garonne',
            'description' => "Des quais de la Daurade au Bikini, de la place du Capitole à la prairie des Filtres\u{a0}: concerts, spectacles, expositions, marchés et soirées dans la Ville rose.",
        ],
        // Nice
        2990440 => [
            'headline' => 'Du Vieux-Nice à la Promenade des Anglais',
            'description' => "Du Vieux-Nice à la Promenade des Anglais, du Carnaval au Nice Jazz Festival\u{a0}: concerts, spectacles, expositions et sorties sur la Côte d'Azur.",
        ],
        // Nantes
        2990969 => [
            'headline' => "Des Machines de l'île au Bouffay",
            'description' => "Des Machines de l'île aux ruelles du Bouffay, du Lieu Unique au Voyage à Nantes\u{a0}: concerts, spectacles, expositions et soirées au bord de la Loire.",
        ],
        // Strasbourg
        2973783 => [
            'headline' => 'De la Petite France à la Neustadt',
            'description' => "De la Petite France à la Neustadt, du Christkindelsmärik aux concerts de la Laiterie\u{a0}: spectacles, expositions et soirées dans la capitale alsacienne.",
        ],
        // Montpellier
        2992166 => [
            'headline' => "De la Comédie aux ruelles de l'Écusson",
            'description' => "De la place de la Comédie aux ruelles de l'Écusson, du Corum au Printemps des Comédiens\u{a0}: concerts, spectacles, expositions et soirées sous le soleil du Languedoc.",
        ],
        // Bordeaux
        3031582 => [
            'headline' => 'Des Chartrons aux quais de la Garonne',
            'description' => "Des Chartrons au miroir d'eau, du Grand-Théâtre à Darwin\u{a0}: concerts, spectacles, expositions et soirées au fil de la Garonne.",
        ],
        // Lille
        2998324 => [
            'headline' => 'Du Vieux-Lille à la Grand-Place',
            'description' => "Des estaminets du Vieux-Lille au Zénith, du Palais des Beaux-Arts à la Braderie\u{a0}: concerts, spectacles, expositions et soirées dans la capitale des Flandres.",
        ],
        // Rennes
        2983990 => [
            'headline' => 'Du marché des Lices aux Trans Musicales',
            'description' => "Du marché des Lices aux Trans Musicales, des places du centre historique à la scène de l'Ubu\u{a0}: concerts, spectacles, expositions et soirées dans la capitale bretonne.",
        ],
        // Saint-Étienne
        2980291 => [
            'headline' => 'De la Cité du design au Zénith',
            'description' => "De la Cité du design au Zénith, du Fil aux places du centre-ville\u{a0}: concerts, spectacles, expositions et soirées dans la ville du design.",
        ],
        // Toulon
        2972328 => [
            'headline' => 'Entre la rade et le mont Faron',
            'description' => "De la rade au mont Faron, de l'Opéra de Toulon aux plages du Mourillon\u{a0}: concerts, spectacles, expositions et sorties sur la côte varoise.",
        ],
        // Grenoble
        3014728 => [
            'headline' => "De la Bastille aux quais de l'Isère",
            'description' => "Des quais de l'Isère au fort de la Bastille, de la MC2 aux salles de concert\u{a0}: spectacles, expositions, festivals et sorties au cœur des Alpes.",
        ],
        // Dijon
        3021372 => [
            'headline' => 'Du palais des Ducs aux halles',
            'description' => "Du palais des ducs de Bourgogne aux halles du marché, de l'Opéra de Dijon à La Vapeur\u{a0}: concerts, spectacles, expositions et sorties en Bourgogne.",
        ],
        // Brest
        3030300 => [
            'headline' => 'Du port aux Ateliers des Capucins',
            'description' => "Du port de commerce aux Ateliers des Capucins, de la Carène au Quartz\u{a0}: concerts, spectacles, expositions et soirées au bout du Finistère.",
        ],
        // Tours
        2972191 => [
            'headline' => 'De la place Plumereau aux bords de Loire',
            'description' => "De la place Plumereau aux guinguettes des bords de Loire, du Grand Théâtre au Temps Machine\u{a0}: concerts, spectacles, expositions et soirées au cœur de la Touraine.",
        ],
        // Clermont-Ferrand
        3024635 => [
            'headline' => 'De la place de Jaude aux volcans',
            'description' => "De la place de Jaude à la Coopérative de Mai, du festival du court métrage aux volcans d'Auvergne\u{a0}: concerts, spectacles, expositions et sorties au cœur du Massif central.",
        ],
        // Orléans
        2989317 => [
            'headline' => 'Du Martroi aux quais de Loire',
            'description' => "De la place du Martroi aux quais de Loire, des fêtes johanniques à l'Astrolabe\u{a0}: concerts, spectacles, expositions et soirées en val de Loire.",
        ],
        // Metz
        2994160 => [
            'headline' => 'De la cathédrale au Centre Pompidou',
            'description' => "De la cathédrale Saint-Étienne au Centre Pompidou-Metz, de l'Arsenal à la BAM\u{a0}: concerts, spectacles, expositions et soirées en Moselle.",
        ],
        // Rouen
        2982652 => [
            'headline' => 'Du Gros-Horloge aux quais de Seine',
            'description' => "Du Gros-Horloge aux quais de Seine, du 106 aux théâtres du centre historique\u{a0}: concerts, spectacles, expositions et soirées dans la capitale normande.",
        ],
        // Nancy
        2990999 => [
            'headline' => 'De la place Stanislas à la Vieille-Ville',
            'description' => "De la place Stanislas à la Vieille-Ville, de l'Opéra national de Lorraine à l'Autre Canal\u{a0}: concerts, spectacles, expositions et soirées dans la cité ducale.",
        ],
    ];

    /**
     * GeoNames id => display order of a metropolis, lowest first: the home page shows the first five. The ones left
     * out come after the ranked ones, the most populated first (Paris, Marseille, Lyon, Toulouse, Nice…).
     */
    private const array DISPLAY_ORDERS = [];

    public function getDescription(): string
    {
        return 'Fill the portal copy of the countries and of the 22 French métropoles, feature France and flag the métropoles for the home page';
    }

    public function up(Schema $schema): void
    {
        // COALESCE: never overwrite what the back office already wrote
        foreach (self::COUNTRIES as $id => $copy) {
            $this->addSql(
                'UPDATE country SET headline = COALESCE(headline, :headline), description = COALESCE(description, :description) WHERE id = :id',
                ['id' => $id] + $copy,
            );
        }

        $this->addSql('UPDATE country SET is_featured = 1 WHERE id = :id', ['id' => self::FEATURED_COUNTRY]);

        foreach (self::METROPOLISES as $id => $copy) {
            $this->addSql(
                'UPDATE admin_zone SET is_metropolis = 1, headline = COALESCE(headline, :headline), description = COALESCE(description, :description) WHERE id = :id',
                ['id' => $id] + $copy,
                ['id' => ParameterType::INTEGER],
            );
        }

        foreach (self::DISPLAY_ORDERS as $id => $displayOrder) {
            $this->addSql(
                'UPDATE admin_zone SET display_order = COALESCE(display_order, :displayOrder) WHERE id = :id',
                ['id' => $id, 'displayOrder' => $displayOrder],
                ['id' => ParameterType::INTEGER, 'displayOrder' => ParameterType::INTEGER],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // NULLIF: only clear the values this migration wrote, not the ones edited since in the back office
        foreach (self::COUNTRIES as $id => $copy) {
            $this->addSql(
                'UPDATE country SET headline = NULLIF(headline, :headline), description = NULLIF(description, :description) WHERE id = :id',
                ['id' => $id] + $copy,
            );
        }

        $this->addSql('UPDATE country SET is_featured = 0 WHERE id = :id', ['id' => self::FEATURED_COUNTRY]);

        foreach (self::METROPOLISES as $id => $copy) {
            $this->addSql(
                'UPDATE admin_zone SET is_metropolis = 0, headline = NULLIF(headline, :headline), description = NULLIF(description, :description) WHERE id = :id',
                ['id' => $id] + $copy,
                ['id' => ParameterType::INTEGER],
            );
        }

        foreach (self::DISPLAY_ORDERS as $id => $displayOrder) {
            $this->addSql(
                'UPDATE admin_zone SET display_order = NULLIF(display_order, :displayOrder) WHERE id = :id',
                ['id' => $id, 'displayOrder' => $displayOrder],
                ['id' => ParameterType::INTEGER, 'displayOrder' => ParameterType::INTEGER],
            );
        }
    }
}
