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
            css_main_reply_block: '.reply-thread',
            css_reply_link: '.reply-link',
            css_replies_container: '.chat-bubbles',
            css_has_loaded_replies: 'has-loaded-replies',
            css_has_shown_replies: 'has-shown-replies',
            css_reply_form_container: '.reply-form-container',
            css_reply_count: '.reply-count',
            css_icon_list: '.icon-list',
            css_main_block_comments: '.comments-container',
            css_load_more_comments: '.load-more',
            css_block_comment: '.chat-item',
            css_block_poster_comment: '.card-body-form',
            css_block_comments: '.chat-bubbles',
            css_heading_comments: '.heading',
            animation_duration: 400,
        }
    }

    /**
     * @returns {ToastManager}
     */
    getToastManager() {
        return window.App.get('toastManager')
    }

    showToast(type, message) {
        const toastManager = this.getToastManager()
        toastManager.createToast(type, message)
    }

    init() {
        const self = this
        $(self.options.css_main_block_comments).each(
            function () // On parcours les div comments (1 par page normalement)
            {
                const commentsContainer = $(this)
                window.App.dispatchPageLoadedEvent(commentsContainer[0]) // On bind les liens connexion/inscription
                self.init_new_comment(commentsContainer) // On bind le formulaire d'envoi d'un nouveau comment
                self.init_load_reply_form(commentsContainer) // On bind le lien de réponse des comments
                self.init_update_reply_count(commentsContainer) // On met à jour le compteur de réponses
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
                const btn = loadMore.find('.btn')
                const parentContainer = loadMore.parent()

                // Add spinner to button
                const originalText = btn.html()
                btn.html(iconHtml(self.options.icon_spinner, 'icon-spin') + ' ' + originalText)
                btn.prop('disabled', true)

                $.get(loadMore.data('url'), function (html) {
                    const $temp = $(html)

                    $temp.each(function() {
                        const item = $(this)
                        self.init_load_reply_form(item)
                        self.init_load_more_comments(item)
                        self.init_update_reply_count(item)
                    })

                    loadMore.replaceWith($temp)
                    self.init_load_more_comments(parentContainer)
                })

                return false
            })
    }

    init_update_reply_count(comments) {
        const self = this
        comments.find(self.options.css_reply_count).each(function () {
            self.update_reply_count($(this).closest(self.options.css_main_reply_block), $(this).html())
        })
    }

    init_load_reply_form(comments) {
        const self = this
        comments
            .find(self.options.css_reply_link)
            .off('click')
            .click(function () // Pour tous les liens répondre
            {
                const link = $(this)

                link.data('original-html', link.html()).html(iconHtml(self.options.icon_spinner, 'icon-spin'))
                const postAnswerContainer = link
                    .closest(self.options.css_main_reply_block)
                    .find(self.options.css_reply_form_container) // On cherche le block du post
                postAnswerContainer.removeClass('is-visible').load(link.data('url'), function () {
                    window.App.dispatchPageLoadedEvent(postAnswerContainer[0]) // On bind les liens connexion/inscription
                    self.init_reply_form(postAnswerContainer) // On bind le formulaire d'envoi d'une nouvelle réponse

                    // Add cancel button handler
                    postAnswerContainer.find('.cancel-reply').off('click').click(function() {
                        postAnswerContainer.removeClass('is-visible')
                        link.html(link.data('original-html'))
                        return false
                    })

                    // Use CSS transition for smooth animation
                    link.html(link.data('original-html'))
                    requestAnimationFrame(() => {
                        $(this).addClass('is-visible')
                        // Focus on textarea after animation starts
                        setTimeout(() => {
                            $(this).find('textarea').focus()
                        }, 150)
                    })
                })
                return false
            })
    }

    update_reply_count(mainAnswerContainer, answerCount) {
        const self = this
        mainAnswerContainer.find(self.options.css_reply_count).html(answerCount)
    }

    init_reply_form(answerPostContainer) {
        const self = this
        $(answerPostContainer)
            .find('form')
            .off('submit')
            .submit(function () {
                window.App.loadingButtons(this)
                const form = $(this)
                const textarea = form.find('textarea')
                const mainAnswerContainer = answerPostContainer.closest(self.options.css_main_reply_block)

                $.post($(this).attr('action'), $(this).serialize())
                    .done(function (retour) {
                        let answerContainer = mainAnswerContainer.find(self.options.css_replies_container)
                        if (retour.success) {
                            // Success - show toast notification
                            self.showToast('success', 'Réponse envoyée avec succès!')

                            // Clear textarea
                            textarea.val('').trigger('blur')

                            // Create replies container if it doesn't exist
                            if (!answerContainer.length) {
                                answerContainer = $('<div class="chat-bubbles replies-container mt-2 ms-5"></div>')
                                mainAnswerContainer.append(answerContainer)
                            }

                            // Add reply with success animation
                            const newReply = $(retour.comment)
                            newReply.addClass('message-sent')
                            answerContainer.prepend(newReply)

                            // Scroll to new reply
                            setTimeout(() => {
                                newReply[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' })
                                setTimeout(() => newReply.removeClass('message-sent'), 500)
                            }, 100)

                            // Hide reply form
                            mainAnswerContainer
                                .find(self.options.css_reply_form_container)
                                .removeClass('is-visible')

                            // Update reply count
                            self.update_reply_count(mainAnswerContainer, retour.reply_count)

                            // Restore reply link
                            const link = mainAnswerContainer.find(self.options.css_reply_link)
                            link.html(link.data('original-html'))
                        } else {
                            // Error - show toast notification
                            self.showToast('error', 'Erreur lors de l\'envoi de la réponse')

                            // Replace form content with error form
                            answerPostContainer.html(retour.post)

                            // Re-initialize form handlers and page events
                            window.App.dispatchPageLoadedEvent(answerPostContainer[0])
                            self.init_reply_form(answerPostContainer)

                            // Re-bind cancel button
                            answerPostContainer.find('.cancel-reply').off('click').click(function() {
                                answerPostContainer.removeClass('is-visible')
                                const link = mainAnswerContainer.find(self.options.css_reply_link)
                                link.html(link.data('original-html'))
                                return false
                            })
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
                                    self.update_reply_count(mainAnswerContainer, '0')
                                    self.init_load_reply_form(mainAnswerContainer)

                                    // Scroll to new comment smoothly
                                    setTimeout(() => {
                                        newComment[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' })
                                        // Remove animation class after animation completes
                                        setTimeout(() => newComment.removeClass('message-sent'), 500)
                                    }, 100)
                                } else {
                                    // Error - show toast notification
                                    self.showToast('error', 'Erreur lors de l\'envoi du commentaire')

                                    // Replace form content with error form
                                    postCommentContainer.html(retour.post)

                                    // Re-initialize form handlers and page events
                                    window.App.dispatchPageLoadedEvent(postCommentContainer[0])
                                    self.init_new_comment(postCommentContainer)
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
