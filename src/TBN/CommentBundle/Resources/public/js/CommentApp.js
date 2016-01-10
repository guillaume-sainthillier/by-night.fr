options = {
    "css_up": "fa-angle-up",
    "css_down": "fa-angle-down",
    "css_spinner": "fa-spinner fa-spin",
    "css_btn_list": ".btn-list",
    "css_main_block_reponse": ".reponses",
    "css_link_repondre": ".repondre",
    "css_block_reponses": ".block_reponses",
    "css_has_loaded_reponse": "has_loaded_reponse",
    "css_has_showed_reponses": "has_showed_reponse",
    "css_block_post_reponse": ".block_poster_reponse",
    "css_nb_reponses": ".nb_reponses",
    "css_icon_list": ".icon_list",
    "css_main_block_comments": ".comments",
    "css_load_more_comments": ".load_more",
    "css_block_comment": ".comment",
    "css_block_poster_commentaire": ".block_poster_commentaire",
    "css_block_commentaires": ".commentaires",
    "css_heading_commentaires": ".heading",
    "animation_duration": 400
};

$(function ()
{
    init_list_comments();
});


function init_list_comments()
{
    $(options.css_main_block_comments).each(function () //On parcours les div comments (1 par page normalement)
    {
        var div_comments = $(this);
        div_comments.load(div_comments.data("url"), function () // Les commentaires sont chargés
        {
            App.initComponents(div_comments); //On bind les liens connexion/inscription            
            init_new_comment(div_comments); //On bind le formulaire d'envoi d'un nouveau commentaire
            init_load_new_reponse(div_comments); //On bind le lien de réponse des commentaires
            init_list_reponses(div_comments); //On bind le bouton de liste des réponses
            init_maj_nb_reponses(div_comments); // On met à jour le boutton de liste en fonction du nombre de réponses
            init_load_more_comments(div_comments);
        });
    });
}

function init_load_more_comments(commentaires)
{
    commentaires.find(options.css_load_more_comments).unbind("click").click(function ()
    {
        var load_more = $(this);

        load_more.find(".btn-block").html("<i class='fa fa-2x " + options.css_spinner + "'></i>");
        load_more.load(load_more.data("url"), function ()
        {
            var block_list_commentaire = load_more.find(options.css_block_commentaires);
            init_load_new_reponse(load_more); //On bind le lien de réponse des commentaires
            init_list_reponses(load_more); //On bind le bouton de liste des réponses
            init_maj_nb_reponses(load_more); // On met à jour le boutton de liste en fonction du nombre de réponses
            init_load_more_comments(load_more);

            var block_commentaires = load_more.closest(options.css_block_commentaires);
            var commentaires = block_list_commentaire.children();
            commentaires.appendTo(block_commentaires);
            block_list_commentaire.closest(options.css_load_more_comments).remove();
        });

        return false;
    });
}

function init_maj_nb_reponses(commentaires)
{
    commentaires.find(options.css_nb_reponses).each(function ()
    {
        maj_nb_reponses($(this).closest(options.css_main_block_reponse), $(this).html());
    });
}

function init_list_reponses(commentaire)
{
    commentaire.find(options.css_btn_list).unbind("click").click(function ()
    {
        var main_block_reponse = $(this)
                .closest(options.css_main_block_reponse);

        var block_reponse = main_block_reponse.find(options.css_block_reponses);
        var icon_list = main_block_reponse.find(options.css_icon_list);

        if (!block_reponse.hasClass(options.css_has_loaded_reponse)) //Les réponses ne sont pas encore chargées
        {
            block_reponse //On masque les liste de réponses et on ajoute la classe css_has_loaded_reponse au block des listes
                    .addClass(options.css_has_loaded_reponse);

            icon_list
                    .removeClass(options.css_up)
                    .addClass(options.css_spinner);

            block_reponse.load($(this).data("url"), function ()
            {
                init_load_more_comments(block_reponse);
                $(this).show(options.animation_duration, function ()
                {
                    $(this).addClass(options.css_has_showed_reponses);
                });

                icon_list
                        .removeClass(options.css_spinner)
                        .removeClass(options.css_up)
                        .addClass(options.css_down);
            });

        } else //Les réponses sont chargées
        {
            if (!block_reponse.hasClass(options.css_has_showed_reponses)) //Les réponses ne sont pas affichées, on les affiche donc
            {
                block_reponse.show(options.animation_duration, function ()
                {
                    $(this).addClass(options.css_has_showed_reponses);
                    icon_list
                            .removeClass(options.css_up)
                            .addClass(options.css_down);
                });
            } else
            {
                block_reponse.hide(options.animation_duration, function ()
                {
                    $(this).removeClass(options.css_has_showed_reponses);
                    icon_list
                            .removeClass(options.css_down)
                            .addClass(options.css_up);
                });
            }
        }
    });
}

function init_load_new_reponse(commentaires)
{
    commentaires.find(options.css_link_repondre).unbind("click").click(function () //Pour tous les liens répondre
    {
        var link = $(this);

        link.data("text", link.text()).html("<i class='fa " + options.css_spinner + "'></i>");
        var block_post_reponse = link.closest(options.css_main_block_reponse).find(options.css_block_post_reponse); // On cherche le block du post
        block_post_reponse.hide().load(link.data("url"), function ()
        {
            App.initComponents(block_post_reponse); //On bind les liens connexion/inscription
            init_new_reponse(block_post_reponse); //On bind le formulaire d'envoi d'une nouvelle réponse
            $(this).show(options.animation_duration, function ()
            {
                link.text(link.data("text"));
            });
        });
        return false;
    });
}

function maj_nb_reponses(main_block_reponses, nb_reponses)
{
    main_block_reponses.find(options.css_btn_list).prop("disabled", (nb_reponses === "0"));
    main_block_reponses.find(options.css_nb_reponses).html(nb_reponses);
}

function init_new_reponse(block_post_reponse)
{
    $(block_post_reponse).find("form").unbind("submit").submit(function ()
    {
        App.loadingButtons(form);
        var form = $(this);
        var main_block_reponses = block_post_reponse.closest(options.css_main_block_reponse);

        $.post(
                $(this).attr("action"),
                $(this).serialize()
                ).done(function (retour)
        {
            var block_reponses = main_block_reponses.find(options.css_block_reponses);
            if (retour.success) //La réponse est envoyée
            {
                block_reponses.prepend(retour.comment); //On ajoute la réponse dans la liste
                main_block_reponses.find(options.css_block_post_reponse).hide(options.animation_duration);
                maj_nb_reponses(main_block_reponses, retour.nb_reponses); //On met à jour le nombre de réponses
                var link = main_block_reponses.find(options.css_link_repondre);

                link.replaceWith(link.text()); //On supprime le lien répondre
            } else //L'envoie de la réponse a échoué
            {
                block_post_reponse.html(retour.post);
                App.initComponents(block_post_reponse); //On bind les liens connexion/inscription
                init_new_reponse(block_post_reponse); //On bind le formulaire d'envoi d'une nouvelle réponse
            }
        }).always(function () //Dans tous les cas
        {
            App.resetButtons(form);
        });

        return false;
    });
}

function init_new_comment(commentaire)
{
    $(commentaire).find("form").each(function ()
    {
        var form = $(this);
        $(this).unbind("submit").submit(function ()
        {
            App.loadingButtons(commentaire); //On bloque le bouton submit le temps du chargement

            $.post(
                    $(this).attr("action"),
                    $(this).serialize()
                    ).done(function (retour) {  //On poste le commentaire 

                var main_block_commentaire = form.closest(options.css_main_block_comments);
                var block_commentaires = main_block_commentaire.find(options.css_block_commentaires);
                var block_poster_commentaire = main_block_commentaire.find(options.css_block_poster_commentaire);

                if (retour.success) //Commentaire bien envoyé
                {
                    block_poster_commentaire.hide(options.animation_duration);
                    block_commentaires.prepend(retour.comment); //On ajoute le commentaire à la liste
                    main_block_commentaire.find(options.css_heading_commentaires).replaceWith(retour.header); //On remplace le heading par le nouveau

                    var main_block_reponse = block_commentaires.find(options.css_block_comment).eq(0);
                    maj_nb_reponses(main_block_reponse, "0"); //On met à jour le nombre de réponses du commentaire
                    init_load_new_reponse(main_block_reponse); //On bind le lien de réponse du commentaire
                    init_list_reponses(main_block_reponse); //On bind le bouton de liste des réponses

                } else //l'envoi du commentaire a échoué
                {
                    block_poster_commentaire.replaceWith(retour.post);
                    var block_poster_commentaire = main_block_commentaire.find(options.css_block_poster_commentaire);
                    App.initComponents(block_poster_commentaire);
                    init_new_comment(block_poster_commentaire);
                }
            }).always(function ()
            {
                App.resetButtons(commentaire);
            });

            return false;
        });
    });
}
