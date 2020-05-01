/*
 * Gestion des collections Symfony2
 *
 * Dépendances: jQuery, bootstrap, js/scripts.js
 *
 * @author Guillaume Sainthillier
 */

$(function() {
    init_collections();
});

/**
 * Initialise les élément de formulaire de type ccmm_collection
 * Pour que l'initialisation fonctionne, l'élement de type DIV doit:
 * - Posséder la classe collection
 * - Déclarer l'attribut data-prototype, correspondant au prototype HTML à dupliquer
 * - Déclarer l'attribut data-item, correspondant au nom de l'entité (ex: Téléphone)
 * - Déclarer l'attribut data-numero, correspondant au libellé du mot numéro (ex: n°)
 * - Déclarer l'attribut data-allow-add, correspondant au libellé du verbe Ajouter (ex: ajouter)
 * - Déclarer l'attribut data-allow-delete, correspondant au libellé du verbe Supprimer (ex: supprimer)
 */
function init_collections() {
    $('.widget_collection').each(function() {
        var $bloc = $(this);
        var $mainLabel = $bloc.prev('label.control-label');
        var childs = $bloc.children('div.form-group');

        // On définit un compteur unique pour nommer les champs qu'on va ajouter dynamiquement
        var index = childs.length;

        // On ajoute un premier champ directement s'il n'en existe pas déjà un (cas d'un nouvel article par exemple).
        if (index === 0) {
            ajouterBloc($bloc, index);
            index++;
        } else {
            // Pour chaque catégorie déjà existante, on ajoute un lien de suppression (si la suppression est autorisée)
            childs.each(function(i) {
                ajouterLienSuppression($(this));
            });
        }

        $('<a href="#" class="btn btn-sm btn-success ml-2"><i class="fa fa-plus"></i></a>')
            .appendTo($mainLabel)
            .click(function(e) {
                ajouterBloc($bloc, index);
                index++;
                e.preventDefault();
                return false;
            });
    });
}

/**
 * Ajout une ligne dans la collection (ligne = entité à recopier + lien de suppression accolé)
 *
 * @param {jQuery} $bloc element jQuery correspondant au div de classe collection
 * @param {number} index l'index de l'item à ajouter
 */
function ajouterBloc($bloc, index) {
    var $prototype = $(
        $bloc
            .data('prototype')
            .replace(/__name__label__/g, index + 1)
            .replace(/__name__/g, index)
    );
    ajouterLienSuppression($prototype);
    $bloc.append($prototype);
    App.initComponents($prototype);
}

/**
 * Ajoute le lien de suppression de la ligne fraîchement créée
 *
 * @param {string} $prototype prototype HTML de la ligne créée
 * @returns {void}
 */
function ajouterLienSuppression($prototype) {
    var $lienSuppression = $(
        '<a href="#" class="btn btn-sm btn-danger btn-sm margin_left_10"><i class="fa fa-times"></i></a>'
    );

    // Ajout du lien
    $prototype.children('label.control-label').append($lienSuppression);

    // Ajout du listener sur le clic du lien
    $lienSuppression.click(function(e) {
        $prototype.remove();
        e.preventDefault(); // évite qu'un # apparaisse dans l'URL
        return false;
    });
}
