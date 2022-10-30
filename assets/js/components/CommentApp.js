export default class CommentApp {
    constructor() {
        this.options = {
            css_up: 'fa-angle-up',
            css_down: 'fa-angle-down',
            css_spinner: 'fa-spinner fa-spin',
            css_btn_list: '.btn-list',
            css_main_block_reponse: '.reponses',
            css_link_repondre: '.repondre',
            css_block_reponses: '.comments',
            css_has_loaded_reponse: 'has_loaded_reponse',
            css_has_showed_reponses: 'has_showed_reponse',
            css_block_post_reponse: '.block_poster_reponse',
            css_nb_reponses: '.nb_reponses',
            css_icon_list: '.icon_list',
            css_main_block_comments: '.comments-container',
            css_load_more_comments: '.load_more',
            css_block_comment: '.comment',
            css_block_poster_comment: '.block_poster_commentaire',
            css_block_comments: '.comments',
            css_heading_comments: '.heading',
            animation_duration: 400,
        };
    }

    init() {
        const self = this;
        $(self.options.css_main_block_comments).each(
            function () // On parcours les div comments (1 par page normalement)
            {
                const commentsContainer = $(this);
                App.dispatchPageLoadedEvent(commentsContainer[0]); // On bind les liens connexion/inscription
                self.init_new_comment(commentsContainer); // On bind le formulaire d'envoi d'un nouveau comment
                self.init_load_new_reponse(commentsContainer); // On bind le lien de réponse des comments
                self.init_list_reponses(commentsContainer); // On bind le bouton de liste des réponses
                self.init_maj_nb_reponses(commentsContainer); // On met à jour le boutton de liste en fonction du nombre de réponses
                self.init_load_more_comments(commentsContainer);
            }
        );
    }

    init_load_more_comments(comments) {
        const self = this;
        comments
            .find(self.options.css_load_more_comments)
            .off('click')
            .click(function () {
                const loadMore = $(this);

                loadMore.find('.btn-block').prepend(`<i class='fa fa-2x ${self.options.css_spinner}'></i>`);
                loadMore.load(loadMore.data('url'), function () {
                    const commentListContainer = loadMore.find(self.options.css_block_comments);
                    self.init_load_new_reponse(loadMore); // On bind le lien de réponse des comments
                    self.init_list_reponses(loadMore); // On bind le bouton de liste des réponses
                    self.init_maj_nb_reponses(loadMore); // On met à jour le boutton de liste en fonction du nombre de réponses
                    self.init_load_more_comments(loadMore);

                    const commentsContainer = loadMore.closest(self.options.css_block_comments);
                    const comments = commentListContainer.children();
                    comments.appendTo(commentsContainer);
                    commentListContainer.closest(self.options.css_load_more_comments).remove();
                });

                return false;
            });
    }

    init_maj_nb_reponses(comments) {
        const self = this;
        comments.find(self.options.css_nb_reponses).each(function () {
            self.maj_nb_reponses($(this).closest(self.options.css_main_block_reponse), $(this).html());
        });
    }

    init_list_reponses(comment) {
        const self = this;
        comment
            .find(self.options.css_btn_list)
            .off('click')
            .click(function () {
                const mainAnswerContainer = $(this).closest(self.options.css_main_block_reponse);

                const answerContainer = mainAnswerContainer.find(self.options.css_block_reponses);
                const iconList = mainAnswerContainer.find(self.options.css_icon_list);

                if (!answerContainer.hasClass(self.options.css_has_loaded_reponse)) {
                    // Les réponses ne sont pas encore chargées
                    answerContainer // On masque les liste de réponses et on ajoute la classe css_has_loaded_reponse au block des listes
                        .addClass(self.options.css_has_loaded_reponse);

                    iconList.removeClass(self.options.css_up).addClass(self.options.css_spinner);

                    answerContainer.load($(this).data('url'), function () {
                        self.init_load_more_comments(answerContainer);
                        $(this).show(self.options.animation_duration, function () {
                            $(this).addClass(self.options.css_has_showed_reponses);
                        });

                        iconList
                            .removeClass(self.options.css_spinner)
                            .removeClass(self.options.css_up)
                            .addClass(self.options.css_down);
                    });
                } // Les réponses sont chargées
                else if (!answerContainer.hasClass(self.options.css_has_showed_reponses)) {
                    // Les réponses ne sont pas affichées, on les affiche donc
                    answerContainer.show(self.options.animation_duration, function () {
                        $(this).addClass(self.options.css_has_showed_reponses);
                        iconList.removeClass(self.options.css_up).addClass(self.options.css_down);
                    });
                } else {
                    answerContainer.hide(self.options.animation_duration, function () {
                        $(this).removeClass(self.options.css_has_showed_reponses);
                        iconList.removeClass(self.options.css_down).addClass(self.options.css_up);
                    });
                }
            });
    }

    init_load_new_reponse(comments) {
        const self = this;
        comments
            .find(self.options.css_link_repondre)
            .off('click')
            .click(function () // Pour tous les liens répondre
            {
                const link = $(this);

                link.data('text', link.text()).html(`<i class='fa ${self.options.css_spinner}'></i>`);
                const postAnswerContainer = link
                    .closest(self.options.css_main_block_reponse)
                    .find(self.options.css_block_post_reponse); // On cherche le block du post
                postAnswerContainer.hide().load(link.data('url'), function () {
                    App.dispatchPageLoadedEvent(postAnswerContainer[0]); // On bind les liens connexion/inscription
                    self.init_new_reponse(postAnswerContainer); // On bind le formulaire d'envoi d'une nouvelle réponse
                    $(this).show(self.options.animation_duration, function () {
                        link.text(link.data('text'));
                    });
                });
                return false;
            });
    }

    maj_nb_reponses(mainAnswerContainer, answerCount) {
        const self = this;
        mainAnswerContainer.find(self.options.css_btn_list).prop('disabled', answerCount === '0');
        mainAnswerContainer.find(self.options.css_nb_reponses).html(answerCount);
    }

    init_new_reponse(answerPostContainer) {
        const self = this;
        $(answerPostContainer)
            .find('form')
            .off('submit')
            .submit(function () {
                App.loadingButtons(this);
                const form = $(this);
                const mainAnswerContainer = answerPostContainer.closest(self.options.css_main_block_reponse);

                $.post($(this).attr('action'), $(this).serialize())
                    .done(function (retour) {
                        const answerContainer = mainAnswerContainer.find(self.options.css_block_reponses);
                        if (retour.success) {
                            // La réponse est envoyée
                            answerContainer.prepend(retour.comment); // On ajoute la réponse dans la liste
                            mainAnswerContainer
                                .find(self.options.css_block_post_reponse)
                                .hide(self.options.animation_duration);
                            self.maj_nb_reponses(mainAnswerContainer, retour.nb_reponses); // On met à jour le nombre de réponses
                            const link = mainAnswerContainer.find(self.options.css_link_repondre);

                            link.replaceWith(link.text()); // On supprime le lien répondre
                        } // L'envoie de la réponse a échoué
                        else {
                            answerPostContainer.html(retour.post);
                            App.dispatchPageLoadedEvent(answerPostContainer[0]); // On bind les liens connexion/inscription
                            self.init_new_reponse(answerPostContainer); // On bind le formulaire d'envoi d'une nouvelle réponse
                        }
                    })
                    .always(function () // Dans tous les cas
                    {
                        App.resetButtons(form);
                    });

                return false;
            });
    }

    init_new_comment(comment) {
        const self = this;
        $(comment)
            .find('form')
            .each(function () {
                const form = $(this);
                $(this)
                    .off('submit')
                    .submit(function () {
                        App.loadingButtons(comment); // On bloque le bouton submit le temps du chargement

                        $.post($(this).attr('action'), $(this).serialize())
                            .done(function (retour) {
                                // On poste le comment

                                const mainCommentsContainer = form.closest(self.options.css_main_block_comments);
                                const commentsContainer = mainCommentsContainer.find(self.options.css_block_comments);
                                let postCommentContainer = mainCommentsContainer.find(
                                    self.options.css_block_poster_comment
                                );

                                if (retour.success) {
                                    // Comment bien envoyé
                                    postCommentContainer.hide(self.options.animation_duration);
                                    commentsContainer.prepend(retour.comment); // On ajoute le comment à la liste
                                    mainCommentsContainer
                                        .find(self.options.css_heading_comments)
                                        .replaceWith(retour.header); // On remplace le heading par le nouveau

                                    const mainAnswerContainer = commentsContainer
                                        .find(self.options.css_block_comment)
                                        .eq(0);
                                    self.maj_nb_reponses(mainAnswerContainer, '0'); // On met à jour le nombre de réponses du comment
                                    self.init_load_new_reponse(mainAnswerContainer); // On bind le lien de réponse du comment
                                    self.init_list_reponses(mainAnswerContainer); // On bind le bouton de liste des réponses
                                } // l'envoi du comment a échoué
                                else {
                                    postCommentContainer.replaceWith(retour.post);
                                    postCommentContainer = mainCommentsContainer.find(
                                        self.options.css_block_poster_comment
                                    );
                                    App.dispatchPageLoadedEvent(postCommentContainer);
                                    self.init_new_comment(postCommentContainer);
                                }
                            })
                            .always(function () {
                                App.resetButtons(comment);
                            });

                        return false;
                    });
            });
    }
}
