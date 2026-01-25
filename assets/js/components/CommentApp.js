import $ from 'jquery'
import ChevronUpIcon from '@/js/icons/lucide/ChevronUp'
import ChevronDownIcon from '@/js/icons/lucide/ChevronDown'
import Loader2Icon from '@/js/icons/lucide/Loader2'
import CheckIcon from '@/js/icons/lucide/Check'
import {iconHtml} from "@/js/components/icons"

export default class CommentApp {
    constructor() {
        this.options = {
            icon_up: ChevronUpIcon,
            icon_down: ChevronDownIcon,
            icon_spinner: Loader2Icon,
            icon_check: CheckIcon,
            css_btn_list: '.btn-list',
            css_main_block_reponse: '.reponses',
            css_link_repondre: '.repondre',
            css_block_reponses: '.chat-bubbles',
            css_has_loaded_reponse: 'has_loaded_reponse',
            css_has_showed_reponses: 'has_showed_reponse',
            css_block_post_reponse: '.block_poster_reponse',
            css_nb_reponses: '.nb_reponses',
            css_icon_list: '.icon_list',
            css_main_block_comments: '.comments-container',
            css_load_more_comments: '.load_more',
            css_block_comment: '.chat-item',
            css_block_poster_comment: '.card-body-form',
            css_block_comments: '.chat-bubbles',
            css_heading_comments: '.heading',
            animation_duration: 400,
        }
    }

    getToastManager() {
        try {
            if (window.App && typeof window.App.get === 'function') {
                return window.App.get('toastManager')
            }
        } catch (e) {
            // ToastManager not available
        }
        return null
    }

    showToast(type, message) {
        const toastManager = this.getToastManager()
        if (toastManager && typeof toastManager[type] === 'function') {
            toastManager[type](message)
        }
    }

    init() {
        const self = this
        $(self.options.css_main_block_comments).each(
            function () // On parcours les div comments (1 par page normalement)
            {
                const commentsContainer = $(this)
                window.App.dispatchPageLoadedEvent(commentsContainer[0]) // On bind les liens connexion/inscription
                self.init_new_comment(commentsContainer) // On bind le formulaire d'envoi d'un nouveau comment
                self.init_load_new_reponse(commentsContainer) // On bind le lien de réponse des comments
                self.init_list_reponses(commentsContainer) // On bind le bouton de liste des réponses
                self.init_maj_nb_reponses(commentsContainer) // On met à jour le boutton de liste en fonction du nombre de réponses
                self.init_load_more_comments(commentsContainer)
            }
        )
    }

    init_load_more_comments(comments) {
        const self = this
        comments
            .find(self.options.css_load_more_comments)
            .off('click')
            .click(function () {
                const loadMore = $(this)

                loadMore.find('.btn-block').prepend(iconHtml(self.options.icon_spinner, 'icon-spin icon-2x'))
                loadMore.load(loadMore.data('url'), function () {
                    const commentListContainer = loadMore.find(self.options.css_block_comments)
                    self.init_load_new_reponse(loadMore) // On bind le lien de réponse des comments
                    self.init_list_reponses(loadMore) // On bind le bouton de liste des réponses
                    self.init_maj_nb_reponses(loadMore) // On met à jour le boutton de liste en fonction du nombre de réponses
                    self.init_load_more_comments(loadMore)

                    const commentsContainer = loadMore.closest(self.options.css_block_comments)
                    const comments = commentListContainer.children()
                    comments.appendTo(commentsContainer)
                    commentListContainer.closest(self.options.css_load_more_comments).remove()
                })

                return false
            })
    }

    init_maj_nb_reponses(comments) {
        const self = this
        comments.find(self.options.css_nb_reponses).each(function () {
            self.maj_nb_reponses($(this).closest(self.options.css_main_block_reponse), $(this).html())
        })
    }

    init_list_reponses(comment) {
        const self = this
        comment
            .find(self.options.css_btn_list)
            .off('click')
            .click(function () {
                const mainAnswerContainer = $(this).closest(self.options.css_main_block_reponse)

                const answerContainer = mainAnswerContainer.find(self.options.css_block_reponses)
                const iconList = mainAnswerContainer.find(self.options.css_icon_list)

                if (!answerContainer.hasClass(self.options.css_has_loaded_reponse)) {
                    // Les réponses ne sont pas encore chargées
                    answerContainer // On masque les liste de réponses et on ajoute la classe css_has_loaded_reponse au block des listes
                        .addClass(self.options.css_has_loaded_reponse)

                    iconList.html(iconHtml(self.options.icon_spinner, 'icon-spin'))

                    answerContainer.load($(this).data('url'), function () {
                        self.init_load_more_comments(answerContainer)
                        $(this).show(self.options.animation_duration, function () {
                            $(this).addClass(self.options.css_has_showed_reponses)
                        })

                        iconList.html(iconHtml(self.options.icon_down))
                    })
                } // Les réponses sont chargées
                else if (!answerContainer.hasClass(self.options.css_has_showed_reponses)) {
                    // Les réponses ne sont pas affichées, on les affiche donc
                    answerContainer.show(self.options.animation_duration, function () {
                        $(this).addClass(self.options.css_has_showed_reponses)
                        iconList.html(iconHtml(self.options.icon_down))
                    })
                } else {
                    answerContainer.hide(self.options.animation_duration, function () {
                        $(this).removeClass(self.options.css_has_showed_reponses)
                        iconList.html(iconHtml(self.options.icon_up))
                    })
                }
            })
    }

    init_load_new_reponse(comments) {
        const self = this
        comments
            .find(self.options.css_link_repondre)
            .off('click')
            .click(function () // Pour tous les liens répondre
            {
                const link = $(this)

                link.data('text', link.text()).html(iconHtml(self.options.icon_spinner, 'icon-spin'))
                const postAnswerContainer = link
                    .closest(self.options.css_main_block_reponse)
                    .find(self.options.css_block_post_reponse) // On cherche le block du post
                postAnswerContainer.hide().load(link.data('url'), function () {
                    window.App.dispatchPageLoadedEvent(postAnswerContainer[0]) // On bind les liens connexion/inscription
                    self.init_new_reponse(postAnswerContainer) // On bind le formulaire d'envoi d'une nouvelle réponse
                    $(this).show(self.options.animation_duration, function () {
                        link.text(link.data('text'))
                    })
                })
                return false
            })
    }

    maj_nb_reponses(mainAnswerContainer, answerCount) {
        const self = this
        mainAnswerContainer.find(self.options.css_btn_list).prop('disabled', answerCount === '0')
        mainAnswerContainer.find(self.options.css_nb_reponses).html(answerCount)
    }

    init_new_reponse(answerPostContainer) {
        const self = this
        $(answerPostContainer)
            .find('form')
            .off('submit')
            .submit(function () {
                window.App.loadingButtons(this)
                const form = $(this)
                const textarea = form.find('textarea')
                const mainAnswerContainer = answerPostContainer.closest(self.options.css_main_block_reponse)

                $.post($(this).attr('action'), $(this).serialize())
                    .done(function (retour) {
                        const answerContainer = mainAnswerContainer.find(self.options.css_block_reponses)
                        if (retour.success) {
                            // Success - show toast notification
                            self.showToast('success', 'Réponse envoyée avec succès!')

                            // Clear textarea
                            textarea.val('').trigger('blur')

                            // Add reply with success animation
                            const newReply = $(retour.comment)
                            newReply.addClass('message-sent')
                            answerContainer.prepend(newReply)

                            // Scroll to new reply
                            setTimeout(() => {
                                newReply[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' })
                                setTimeout(() => newReply.removeClass('message-sent'), 500)
                            }, 100)

                            mainAnswerContainer
                                .find(self.options.css_block_post_reponse)
                                .hide(self.options.animation_duration)
                            self.maj_nb_reponses(mainAnswerContainer, retour.nb_reponses)
                            const link = mainAnswerContainer.find(self.options.css_link_repondre)

                            link.replaceWith(link.text())
                        } else {
                            // Error - show toast notification
                            self.showToast('error', 'Erreur lors de l\'envoi de la réponse')

                            answerPostContainer.html(retour.post)
                            if (answerPostContainer.length) {
                                window.App.dispatchPageLoadedEvent(answerPostContainer[0])
                                self.init_new_reponse(answerPostContainer)
                            }
                        }
                    })
                    .fail(function () {
                        self.showToast('error', 'Une erreur est survenue')
                    })
                    .always(function () {
                        window.App.resetButtons(form)
                    })

                return false
            })
    }

    init_new_comment(comment) {
        const self = this
        $(comment)
            .find('form')
            .each(function () {
                const form = $(this)
                const textarea = form.find('textarea')

                $(this)
                    .off('submit')
                    .submit(function () {
                        window.App.loadingButtons(comment)

                        // Create temporary comment element for optimistic UI
                        const tempComment = $('<div class="comment is-sending">')

                        $.post($(this).attr('action'), $(this).serialize())
                            .done(function (retour) {
                                const mainCommentsContainer = form.closest(self.options.css_main_block_comments)
                                const commentsContainer = mainCommentsContainer.find(self.options.css_block_comments)
                                let postCommentContainer = mainCommentsContainer.find(
                                    self.options.css_block_poster_comment
                                )

                                if (retour.success) {
                                    // Success - show toast notification
                                    self.showToast('success', 'Commentaire envoyé avec succès!')

                                    // Clear textarea with animation
                                    textarea.val('').trigger('blur')

                                    // Update comment counter
                                    if (retour.count !== undefined) {
                                        const heading = mainCommentsContainer.find(self.options.css_heading_comments)
                                        const plural = retour.count > 1 ? 's' : ''
                                        heading.find('span').text(`${retour.count} Commentaire${plural}`)
                                        // Remove "Soyez le premier à réagir" message if it exists
                                        heading.find('small').remove()
                                    }

                                    // Check if comments body container exists
                                    let commentsBodyContainer = mainCommentsContainer.find('.card-body.scrollable')

                                    // If no comments container exists yet (first comment), create one
                                    if (!commentsBodyContainer.length) {
                                        commentsBodyContainer = $('<div class="card-body scrollable" style="max-height: 600px"></div>')
                                        const chatDiv = $('<div class="chat"></div>')
                                        const chatBubblesDiv = $('<div class="chat-bubbles"></div>')
                                        chatDiv.append(chatBubblesDiv)
                                        commentsBodyContainer.append(chatDiv)
                                        mainCommentsContainer.find('.card').append(commentsBodyContainer)
                                    }

                                    // Get the chat bubbles container
                                    const commentsList = commentsBodyContainer.find('.chat-bubbles')

                                    // Add new comment with success animation
                                    const newComment = $(retour.comment)
                                    newComment.addClass('message-sent')
                                    commentsList.prepend(newComment)

                                    // Initialize listeners for the new comment
                                    const mainAnswerContainer = commentsList.find(self.options.css_block_comment).eq(0)
                                    self.maj_nb_reponses(mainAnswerContainer, '0')
                                    self.init_load_new_reponse(mainAnswerContainer)
                                    self.init_list_reponses(mainAnswerContainer)

                                    // Scroll to new comment smoothly
                                    setTimeout(() => {
                                        newComment[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' })
                                        // Remove animation class after animation completes
                                        setTimeout(() => newComment.removeClass('message-sent'), 500)
                                    }, 100)
                                } else {
                                    // Error - show toast notification
                                    self.showToast('error', 'Erreur lors de l\'envoi du commentaire')

                                    postCommentContainer.replaceWith(retour.post)
                                    postCommentContainer = mainCommentsContainer.find(
                                        self.options.css_block_poster_comment
                                    )
                                    if (postCommentContainer.length) {
                                        window.App.dispatchPageLoadedEvent(postCommentContainer[0])
                                        self.init_new_comment(postCommentContainer)
                                    }
                                }
                            })
                            .fail(function () {
                                self.showToast('error', 'Une erreur est survenue')
                            })
                            .always(function () {
                                window.App.resetButtons(comment)
                                tempComment.remove()
                            })

                        return false
                    })
            })
    }
}
