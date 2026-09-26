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

final class Version20260925184542 extends AbstractMigration
{
    /**
     * [city id, its slug, id of the smaller namesake holding the bare slug, the bare slug] for each city of 20,000
     * inhabitants or more whose name another zone got first: Gedmo gives the bare slug to whichever namesake the
     * GeoNames import inserted first, so /toulon was a hamlet of Vienne and the real Toulon lived at /toulon-1.
     * The most populated namesake wins, whatever its country. Listed from the production copy of 2026-09.
     */
    private const array SWAPS = [
        // Le Havre (Seine-Maritime), over Le Havre (Manche)
        [3003796, 'le-havre-1', 3003795, 'le-havre'],
        // Saint-Étienne (Loire), over Saint-Étienne (Martinique)
        [2980291, 'saint-etienne-2', 11599508, 'saint-etienne'],
        // Toulon (Var), over Toulon (Vienne)
        [2972328, 'toulon-1', 2972327, 'toulon'],
        // Saint-Denis (Réunion), over Saint-Denis (Puy-de-Dôme)
        [935264, 'saint-denis-11', 2980912, 'saint-denis'],
        // Saint-Paul (Réunion), over Saint-Paul (Savoie)
        [935221, 'saint-paul-15', 2977639, 'saint-paul'],
        // Mons (Province du Hainaut), over Mons (Puy-de-Dôme)
        [2790869, 'mons-12', 2993220, 'mons'],
        // Montreuil (Seine-Saint-Denis), over Montreuil (Pas-de-Calais)
        [2992090, 'montreuil-2', 2992061, 'montreuil'],
        // Pau (Pyrénées-Atlantiques), over Pau (Savoie)
        [2988358, 'pau-1', 2988357, 'pau'],
        // La Rochelle (Charente-Maritime), over La Rochelle (Loiret)
        [3006787, 'la-rochelle-3', 3006784, 'la-rochelle'],
        // La Louvière (Province du Hainaut), over La Louvière (Eure-et-Loir)
        [2793508, 'la-louviere-4', 3008412, 'la-louviere'],
        // Saint-Pierre (Réunion), over Saint-Pierre (Pas-de-Calais)
        [935214, 'saint-pierre-22', 2977566, 'saint-pierre'],
        // Mérignac (Gironde), over Mérignac (Charente)
        [2994393, 'merignac-2', 2994391, 'merignac'],
        // Hasselt (Provincie Limburg), over Hasselt (Provincie Vlaams-Brabant)
        [2796491, 'hasselt-1', 2796490, 'hasselt'],
        // Valence (Drôme), over Valence (Charente)
        [2971053, 'valence-2', 2971051, 'valence'],
        // Saint-Quentin (Aisne), over Saint-Quentin (Pas-de-Calais)
        [2977295, 'saint-quentin-1', 2977294, 'saint-quentin'],
        // Vannes (Morbihan), over Vannes (Aube)
        [2970777, 'vannes-1', 2970776, 'vannes'],
        // Arles (Bouches-du-Rhône), over Arles (Pyrénées-Orientales)
        [3036938, 'arles-1', 3036935, 'arles'],
        // Châteauroux (Indre), over Châteauroux (Orne)
        [3026204, 'chateauroux-1', 3026203, 'chateauroux'],
        // Fréjus (Var), over Fréjus (Hautes-Alpes)
        [3017253, 'frejus-1', 3017252, 'frejus'],
        // Montauban (Tarn-et-Garonne), over Montauban (Haute-Marne)
        [2993002, 'montauban-1', 2993001, 'montauban'],
        // Saint-André (Réunion), over Saint-André (Charente)
        [935268, 'saint-andre-8', 2981780, 'saint-andre'],
        // Saint-Louis (Réunion), over Saint-Louis (Bouches-du-Rhône)
        [935223, 'saint-louis-11', 2978738, 'saint-louis'],
        // Castres (Tarn), over Castres (Aisne)
        [3028263, 'castres-2', 3028261, 'castres'],
        // Chelles (Seine-et-Marne), over Chelles (Oise)
        [3025622, 'chelles-1', 3025621, 'chelles'],
        // Saint-Priest (Rhône), over Saint-Priest (Creuse)
        [2977356, 'saint-priest-1', 2977355, 'saint-priest'],
        // Le Port (Réunion), over Le Port (Nièvre)
        [935616, 'le-port-6', 3002555, 'le-port'],
        // Saint-Ouen (Seine-Saint-Denis), over Saint-Ouen (Somme)
        [2977824, 'saint-ouen-3', 2977821, 'saint-ouen'],
        // Bagneux (Hauts-de-Seine), over Bagneux (Aisne)
        [3035409, 'bagneux-1', 3035408, 'bagneux'],
        // Massy (Essonne), over Massy (Seine-Maritime)
        [2995206, 'massy-1', 2995205, 'massy'],
        // Vitrolles (Bouches-du-Rhône), over Vitrolles (Hautes-Alpes)
        [2967870, 'vitrolles-1', 2967868, 'vitrolles'],
        // Lokeren (Provincie Oost-Vlaanderen), over Lokeren (Provincie Antwerpen)
        [2792196, 'lokeren-1', 2792195, 'lokeren'],
        // Saint-Benoît (Réunion), over Saint-Benoît (Yvelines)
        [935267, 'saint-benoit-5', 2981407, 'saint-benoit'],
        // Saint-Raphaël (Var), over Saint-Raphaël (Dordogne)
        [2977246, 'saint-raphael-1', 2977245, 'saint-raphael'],
        // Halle (Provincie Vlaams-Brabant), over Hallé (Indre)
        [2796696, 'halle-2', 3014051, 'halle'],
        // Saint-Joseph (Réunion), over Saint-Joseph (Manche)
        [935227, 'saint-joseph-14', 2979167, 'saint-joseph'],
        // Fribourg (Sarine District), over Fribourg (Moselle)
        [2660718, 'fribourg-1', 3017047, 'fribourg'],
        // Châtillon (Hauts-de-Seine), over Châtillon (Rhône)
        [3026083, 'chatillon-1', 3026075, 'chatillon'],
        // Sainte-Marie (Réunion), over Sainte-Marie (Pyrénées-Orientales)
        [935255, 'sainte-marie-16', 2980416, 'sainte-marie'],
        // Baie-Mahault (Guadeloupe), over Baie Mahault (Guadeloupe)
        [3579767, 'baie-mahault-1', 3579766, 'baie-mahault'],
        // Orange (Vaucluse), over Orange (Haute-Savoie)
        [2989460, 'orange-1', 2989459, 'orange'],
        // Rochefort (Charente-Maritime), over Rochefort (Mayenne)
        [2983276, 'rochefort-2', 2983273, 'rochefort'],
        // Le Chesnay (Yvelines), over Le Chesnay (Eure)
        [3004630, 'le-chesnay-1', 3004629, 'le-chesnay'],
        // Chaumont (Haute-Marne), over Chaumont (Seine-Maritime)
        [3025892, 'chaumont-3', 3025889, 'chaumont'],
        // La Garde (Var), over La Garde (Côtes-d’Armor)
        [3009223, 'la-garde-20', 3009198, 'la-garde'],
        // Lunel (Hérault), over Lunel (Aveyron)
        [2997116, 'lunel-1', 2997115, 'lunel'],
        // Fresnes (Val-de-Marne), over Fresnes (Aisne)
        [3017178, 'fresnes-1', 3017169, 'fresnes'],
        // Fontaine (Isère), over Fontaine (Nord)
        [3018095, 'fontaine-12', 3018082, 'fontaine'],
        // Torcy (Seine-et-Marne), over Torcy (Pas-de-Calais)
        [2972444, 'torcy-1', 2972443, 'torcy'],
        // Moulins (Allier), over Moulins (Aisne)
        [2991481, 'moulins-3', 2991478, 'moulins'],
        // Montreux (Riviera-Pays-d'Enhaut District), over Montreux (Meurthe-et-Moselle)
        [2659601, 'montreux-1', 2992058, 'montreux'],
        // Sainte-Anne (Guadeloupe), over Sainte-Anne (Var)
        [3578466, 'sainte-anne-11', 2980772, 'sainte-anne'],
        // Olivet (Loiret), over Olivet (Mayenne)
        [2989611, 'olivet-1', 2989610, 'olivet'],
        // Saint-Nicolas (Province de Liège), over Saint-Nicolas (Nord)
        [2787356, 'saint-nicolas-4', 2977900, 'saint-nicolas'],
        // Sainte-Suzanne (Réunion), over Sainte-Suzanne (Mayenne)
        [935248, 'sainte-suzanne-4', 2980304, 'sainte-suzanne'],
    ];

    public function getDescription(): string
    {
        return 'Give the bare slug of a name (/toulon, /pau, /le-havre…) to its most populated city when it has 20,000 inhabitants or more, and that city\'s -N slug to the smaller namesake that held it';
    }

    public function up(Schema $schema): void
    {
        foreach (self::SWAPS as [$cityId, $citySlug, $holderId, $bareSlug]) {
            $this->exchange($holderId, $bareSlug, $cityId, $citySlug);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::SWAPS as [$cityId, $citySlug, $holderId, $bareSlug]) {
            $this->exchange($cityId, $bareSlug, $holderId, $citySlug);
        }
    }

    /**
     * Exchanges the slugs of two zones. The slug is unique and MySQL checks it row by row, so the first zone waits on
     * a temporary slug. Each step only touches a zone that still has the slug it expects: a slug taken meanwhile by a
     * third zone fails the migration, rolled back, rather than moving the wrong rows.
     */
    private function exchange(int $firstId, string $firstSlug, int $secondId, string $secondSlug): void
    {
        $temporary = $firstSlug . '--swap';

        $this->addSql(
            'UPDATE admin_zone SET slug = :temporary WHERE id = :id AND slug = :slug',
            ['id' => $firstId, 'slug' => $firstSlug, 'temporary' => $temporary],
            ['id' => ParameterType::INTEGER],
        );
        $this->addSql(
            'UPDATE admin_zone SET slug = :newSlug WHERE id = :id AND slug = :slug',
            ['id' => $secondId, 'slug' => $secondSlug, 'newSlug' => $firstSlug],
            ['id' => ParameterType::INTEGER],
        );
        $this->addSql(
            'UPDATE admin_zone SET slug = :newSlug WHERE id = :id AND slug = :slug',
            ['id' => $firstId, 'slug' => $temporary, 'newSlug' => $secondSlug],
            ['id' => ParameterType::INTEGER],
        );
    }
}
