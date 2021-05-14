export default class CommentApp {
    constructor() {
        this.options = {
            css_up: 'fa-angle-up',
            css_down: 'fa-angle-down',
            css_spinner: 'fa-spinner fa-spin',
            css_btn_list: '.btn-list',
            css_main_block_reponse: '.reponses',
            css_link_repondre: '.repondre',
            css_block_reponses: '.block_reponses',
            css_has_loaded_reponse: 'has_loaded_reponse',
            css_has_showed_reponses: 'has_showed_reponse',
            css_block_post_reponse: '.block_poster_reponse',
            css_nb_reponses: '.nb_reponses',
            css_icon_list: '.icon_list',
            css_main_block_comments: '.comments',
            css_load_more_comments: '.load_more',
            css_block_comment: '.comment',
            css_block_poster_commentaire: '.block_poster_commentaire',
            css_block_commentaires: '.commentaires',
            css_heading_commentaires: '.heading',
            animation_duration: 400,
        };
    }

    init() {
        const self = this;
        $(self.options.css_main_block_comments).each(function () //On parcours les div comments (1 par page normalement)
        {
            var div_comments = $(this);
            App.dispatchPageLoadedEvent(div_comments[0]); //On bind les liens connexion/inscription
            self.init_new_comment(div_comments); //On bind le formulaire d'envoi d'un nouveau commentaire
            self.init_load_new_reponse(div_comments); //On bind le lien de réponse des commentaires
            self.init_list_reponses(div_comments); //On bind le bouton de liste des réponses
            self.init_maj_nb_reponses(div_comments); // On met à jour le boutton de liste en fonction du nombre de réponses
            self.init_load_more_comments(div_comments);
        });
    }

    init_load_more_comments(commentaires) {
        const self = this;
        commentaires
            .find(self.options.css_load_more_comments)
            .off('click')
            .click(function () {
                var load_more = $(this);

                load_more.find('.btn-block').prepend("<i class='fa fa-2x " + self.options.css_spinner + "'></i>");
                load_more.load(load_more.data('url'), function () {
                    var block_list_commentaire = load_more.find(self.options.css_block_commentaires);
                    self.init_load_new_reponse(load_more); //On bind le lien de réponse des commentaires
                    self.init_list_reponses(load_more); //On bind le bouton de liste des réponses
                    self.init_maj_nb_reponses(load_more); // On met à jour le boutton de liste en fonction du nombre de réponses
                    self.init_load_more_comments(load_more);

                    var block_commentaires = load_more.closest(self.options.css_block_commentaires);
                    var commentaires = block_list_commentaire.children();
                    commentaires.appendTo(block_commentaires);
                    block_list_commentaire.closest(self.options.css_load_more_comments).remove();
                });

                return false;
            });
    }

    init_maj_nb_reponses(commentaires) {
        const self = this;
        commentaires.find(self.options.css_nb_reponses).each(function () {
            self.maj_nb_reponses($(this).closest(self.options.css_main_block_reponse), $(this).html());
        });
    }

    init_list_reponses(commentaire) {
        const self = this;
        commentaire
            .find(self.options.css_btn_list)
            .off('click')
            .click(function () {
                var main_block_reponse = $(this).closest(self.options.css_main_block_reponse);

                var block_reponse = main_block_reponse.find(self.options.css_block_reponses);
                var icon_list = main_block_reponse.find(self.options.css_icon_list);

                if (!block_reponse.hasClass(self.options.css_has_loaded_reponse)) {
                    //Les réponses ne sont pas encore chargées
                    block_reponse //On masque les liste de réponses et on ajoute la classe css_has_loaded_reponse au block des listes
                        .addClass(self.options.css_has_loaded_reponse);

                    icon_list.removeClass(self.options.css_up).addClass(self.options.css_spinner);

                    block_reponse.load($(this).data('url'), function () {
                        self.init_load_more_comments(block_reponse);
                        $(this).show(self.options.animation_duration, function () {
                            $(this).addClass(self.options.css_has_showed_reponses);
                        });

                        icon_list
                            .removeClass(self.options.css_spinner)
                            .removeClass(self.options.css_up)
                            .addClass(self.options.css_down);
                    });
                } //Les réponses sont chargées
                else {
                    if (!block_reponse.hasClass(self.options.css_has_showed_reponses)) {
                        //Les réponses ne sont pas affichées, on les affiche donc
                        block_reponse.show(self.options.animation_duration, function () {
                            $(this).addClass(self.options.css_has_showed_reponses);
                            icon_list.removeClass(self.options.css_up).addClass(self.options.css_down);
                        });
                    } else {
                        block_reponse.hide(self.options.animation_duration, function () {
                            $(this).removeClass(self.options.css_has_showed_reponses);
                            icon_list.removeClass(self.options.css_down).addClass(self.options.css_up);
                        });
                    }
                }
            });
    }

    init_load_new_reponse(commentaires) {
        const self = this;
        commentaires
            .find(self.options.css_link_repondre)
            .off('click')
            .click(function () //Pour tous les liens répondre
            {
                var link = $(this);

                link.data('text', link.text()).html("<i class='fa " + self.options.css_spinner + "'></i>");
                var block_post_reponse = link
                    .closest(self.options.css_main_block_reponse)
                    .find(self.options.css_block_post_reponse); // On cherche le block du post
                block_post_reponse.hide().load(link.data('url'), function () {
                    App.dispatchPageLoadedEvent(block_post_reponse[0]); //On bind les liens connexion/inscription
                    self.init_new_reponse(block_post_reponse); //On bind le formulaire d'envoi d'une nouvelle réponse
                    $(this).show(self.options.animation_duration, function () {
                        link.text(link.data('text'));
                    });
                });
                return false;
            });
    }

    maj_nb_reponses(main_block_reponses, nb_reponses) {
        const self = this;
        main_block_reponses.find(self.options.css_btn_list).prop('disabled', nb_reponses === '0');
        main_block_reponses.find(self.options.css_nb_reponses).html(nb_reponses);
    }

    init_new_reponse(block_post_reponse) {
        const self = this;
        $(block_post_reponse)
            .find('form')
            .off('submit')
            .submit(function () {
                App.loadingButtons(this);
                var form = $(this);
                var main_block_reponses = block_post_reponse.closest(self.options.css_main_block_reponse);

                $.post($(this).attr('action'), $(this).serialize())
                    .done(function (retour) {
                        var block_reponses = main_block_reponses.find(self.options.css_block_reponses);
                        if (retour.success) {
                            //La réponse est envoyée
                            block_reponses.prepend(retour.comment); //On ajoute la réponse dans la liste
                            main_block_reponses
                                .find(self.options.css_block_post_reponse)
                                .hide(self.options.animation_duration);
                            self.maj_nb_reponses(main_block_reponses, retour.nb_reponses); //On met à jour le nombre de réponses
                            var link = main_block_reponses.find(self.options.css_link_repondre);

                            link.replaceWith(link.text()); //On supprime le lien répondre
                        } //L'envoie de la réponse a échoué
                        else {
                            block_post_reponse.html(retour.post);
                            App.dispatchPageLoadedEvent(block_post_reponse[0]); //On bind les liens connexion/inscription
                            self.init_new_reponse(block_post_reponse); //On bind le formulaire d'envoi d'une nouvelle réponse
                        }
                    })
                    .always(function () //Dans tous les cas
                    {
                        App.resetButtons(form);
                    });

                return false;
            });
    }

    init_new_comment(commentaire) {
        const self = this;
        $(commentaire)
            .find('form')
            .each(function () {
                var form = $(this);
                $(this)
                    .off('submit')
                    .submit(function () {
                        App.loadingButtons(commentaire); //On bloque le bouton submit le temps du chargement

                        $.post($(this).attr('action'), $(this).serialize())
                            .done(function (retour) {
                                //On poste le commentaire

                                var main_block_commentaire = form.closest(self.options.css_main_block_comments);
                                var block_commentaires = main_block_commentaire.find(
                                    self.options.css_block_commentaires
                                );
                                var block_poster_commentaire = main_block_commentaire.find(
                                    self.options.css_block_poster_commentaire
                                );

                                if (retour.success) {
                                    //Commentaire bien envoyé
                                    block_poster_commentaire.hide(self.options.animation_duration);
                                    block_commentaires.prepend(retour.comment); //On ajoute le commentaire à la liste
                                    main_block_commentaire
                                        .find(self.options.css_heading_commentaires)
                                        .replaceWith(retour.header); //On remplace le heading par le nouveau

                                    var main_block_reponse = block_commentaires
                                        .find(self.options.css_block_comment)
                                        .eq(0);
                                    self.maj_nb_reponses(main_block_reponse, '0'); //On met à jour le nombre de réponses du commentaire
                                    self.init_load_new_reponse(main_block_reponse); //On bind le lien de réponse du commentaire
                                    self.init_list_reponses(main_block_reponse); //On bind le bouton de liste des réponses
                                } //l'envoi du commentaire a échoué
                                else {
                                    block_poster_commentaire.replaceWith(retour.post);
                                    block_poster_commentaire = main_block_commentaire.find(
                                        self.options.css_block_poster_commentaire
                                    );
                                    App.dispatchPageLoadedEvent(block_poster_commentaire);
                                    self.init_new_comment(block_poster_commentaire);
                                }
                            })
                            .always(function () {
                                App.resetButtons(commentaire);
                            });

                        return false;
                    });
            });
    }
}
