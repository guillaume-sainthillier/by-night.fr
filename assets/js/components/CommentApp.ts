import { renderIcon } from '@silarhi/ux-icons-js'
import $ from 'jquery'
import type { SweetAlertIcon } from 'sweetalert2'
import { LucideLoader2Icon } from '@/js/icons'
import type ToastManager from '@/js/services/modals/ToastManager'
import { loadingButtons, resetButtons } from '@/js/utils/buttons'
import { plural } from '@/js/utils/plural'

/**
 * What CommentController::new answers to a comment posted: the comment rendered, or the form with its errors
 */
type CommentResponse =
    | { success: true; comment: string; count: number; header: string }
    | { success: false; post: string }

/**
 * What ReplyController::new answers to a reply posted: the reply rendered, or the form with its errors
 */
type ReplyResponse = { success: true; comment: string; reply_count: number } | { success: false; post: string }

export default class CommentApp {
    options = {
        icon_spinner: LucideLoader2Icon,
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
        css_comments_count: '.comments-count',
        animation_duration: 400,
    }

    getToastManager(): ToastManager {
        return window.App.get('toastManager')
    }

    showToast(type: SweetAlertIcon, message: string) {
        const toastManager = this.getToastManager()
        toastManager.createToast(type, message)
    }

    init() {
        $(this.options.css_main_block_comments).each((_index, element) => {
            // Iterate through comments divs (normally 1 per page)
            const commentsContainer = $(element)
            window.App.mount(element) // Bind login/register links
            this.init_new_comment(commentsContainer) // Bind new comment form submission
            this.init_load_reply_form(commentsContainer) // Bind reply links for comments
            this.init_update_reply_count(commentsContainer) // Update reply counter
            this.init_load_more_comments(commentsContainer)
        })
    }

    init_load_more_comments(comments: JQuery) {
        comments
            .find(this.options.css_load_more_comments)
            .off('click')
            .click((event) => {
                const loadMore = $(event.currentTarget)
                const btn = loadMore.find('.btn')
                const parentContainer = loadMore.parent()

                // Add spinner to button
                const originalText = btn.html()
                btn.html(`${renderIcon(this.options.icon_spinner, { class: 'icon icon-spin' })} ${originalText}`)
                btn.prop('disabled', true)

                const url: string = loadMore.data('url')
                $.get(url, (html: string) => {
                    const $temp = $(html)

                    $temp.each((_index, element) => {
                        const item = $(element)
                        this.init_load_reply_form(item)
                        this.init_load_more_comments(item)
                        this.init_update_reply_count(item)
                    })

                    loadMore.replaceWith($temp)
                    this.init_load_more_comments(parentContainer)
                })

                return false
            })
    }

    init_update_reply_count(comments: JQuery) {
        comments.find(this.options.css_reply_count).each((_index, element) => {
            this.update_reply_count($(element).closest(this.options.css_main_reply_block), $(element).html())
        })
    }

    init_load_reply_form(comments: JQuery) {
        const self = this
        comments
            .find(self.options.css_reply_link)
            .off('click')
            .click((event) => {
                // For all reply links
                const link = $(event.currentTarget)

                link.data('original-html', link.html()).html(
                    renderIcon(self.options.icon_spinner, { class: 'icon icon-spin' })
                )
                const postAnswerContainer = link
                    .closest(self.options.css_main_reply_block)
                    .find(self.options.css_reply_form_container) // Find the post block
                const url: string = link.data('url')
                postAnswerContainer.removeClass('is-visible').load(url, function (this: HTMLElement) {
                    window.App.mount(postAnswerContainer[0]) // Bind login/register links
                    self.init_reply_form(postAnswerContainer) // Bind new reply form submission

                    // Add cancel button handler
                    postAnswerContainer
                        .find('.cancel-reply')
                        .off('click')
                        .click(() => {
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

    update_reply_count(mainAnswerContainer: JQuery, answerCount: string) {
        mainAnswerContainer.find(this.options.css_reply_count).html(answerCount)
    }

    init_reply_form(answerPostContainer: JQuery) {
        $(answerPostContainer)
            .find('form')
            .off('submit')
            .submit((event) => {
                const formElement = event.currentTarget
                loadingButtons(formElement)
                const form = $(formElement)
                const textarea = form.find('textarea')
                const mainAnswerContainer = answerPostContainer.closest(this.options.css_main_reply_block)

                $.post(form.attr('action') ?? '', form.serialize())
                    .done((response: ReplyResponse) => {
                        let answerContainer = mainAnswerContainer.find(this.options.css_replies_container)
                        if (response.success) {
                            // Success - show toast notification
                            this.showToast('success', 'Réponse envoyée avec succès !')

                            // Clear textarea
                            textarea.val('').trigger('blur')

                            // Create replies container if it doesn't exist
                            if (!answerContainer.length) {
                                answerContainer = $('<div class="chat-bubbles replies-container mt-2 ms-5"></div>')
                                mainAnswerContainer.append(answerContainer)
                            }

                            // Add reply with success animation
                            const newReply = $(response.comment)
                            newReply.addClass('message-sent')
                            answerContainer.prepend(newReply)

                            // Scroll to new reply
                            setTimeout(() => {
                                newReply[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' })
                                setTimeout(() => newReply.removeClass('message-sent'), 500)
                            }, 100)

                            // Hide reply form
                            mainAnswerContainer.find(this.options.css_reply_form_container).removeClass('is-visible')

                            // Update reply count
                            this.update_reply_count(mainAnswerContainer, String(response.reply_count))

                            // Restore reply link
                            const link = mainAnswerContainer.find(this.options.css_reply_link)
                            link.html(link.data('original-html'))
                        } else {
                            // Error - show toast notification
                            this.showToast('error', "Erreur lors de l'envoi de la réponse")

                            // Replace form content with error form
                            answerPostContainer.html(response.post)

                            // Re-initialize form handlers and page events
                            window.App.mount(answerPostContainer[0])
                            this.init_reply_form(answerPostContainer)

                            // Re-bind cancel button
                            answerPostContainer
                                .find('.cancel-reply')
                                .off('click')
                                .click(() => {
                                    answerPostContainer.removeClass('is-visible')
                                    const link = mainAnswerContainer.find(this.options.css_reply_link)
                                    link.html(link.data('original-html'))
                                    return false
                                })
                        }
                    })
                    .fail(() => {
                        this.showToast('error', 'Une erreur est survenue')
                    })
                    .always(() => {
                        resetButtons(formElement)
                    })

                return false
            })
    }

    init_new_comment(comment: JQuery) {
        $(comment)
            .find('form')
            .each((_index, element) => {
                const form = $(element)
                const textarea = form.find('textarea')

                form.off('submit').submit(() => {
                    loadingButtons(comment)

                    // Create temporary comment element for optimistic UI
                    const tempComment = $('<div class="comment is-sending">')

                    $.post(form.attr('action') ?? '', form.serialize())
                        .done((response: CommentResponse) => {
                            const mainCommentsContainer = form.closest(this.options.css_main_block_comments)
                            const postCommentContainer = mainCommentsContainer.find(
                                this.options.css_block_poster_comment
                            )

                            if (response.success) {
                                // Success - show toast notification
                                this.showToast('success', 'Commentaire envoyé avec succès !')

                                // Clear textarea with animation
                                textarea.val('').trigger('blur')

                                // Update comment counter
                                if (response.count !== undefined) {
                                    mainCommentsContainer.find(this.options.css_comments_count).text(
                                        plural(response.count, {
                                            one: '# commentaire',
                                            other: '# commentaires',
                                        })
                                    )
                                }

                                // Check if comments body container exists
                                let commentsBodyContainer = mainCommentsContainer.find('.card-body.scrollable')

                                // If no comments container exists yet (first comment), create one above the form
                                if (!commentsBodyContainer.length) {
                                    commentsBodyContainer = $(
                                        '<div class="card-body scrollable" style="max-height: 600px"></div>'
                                    )
                                    const chatDiv = $('<div class="chat"></div>')
                                    const chatBubblesDiv = $('<div class="chat-bubbles"></div>')
                                    chatDiv.append(chatBubblesDiv)
                                    commentsBodyContainer.append(chatDiv)
                                    postCommentContainer.before(commentsBodyContainer)
                                }

                                // Get the chat bubbles container
                                const commentsList = commentsBodyContainer.find('.chat-bubbles')

                                // Add new comment with success animation
                                const newComment = $(response.comment)
                                newComment.addClass('message-sent')
                                commentsList.prepend(newComment)

                                // Initialize listeners for the new comment
                                const mainAnswerContainer = commentsList.find(this.options.css_block_comment).eq(0)
                                this.update_reply_count(mainAnswerContainer, '0')
                                this.init_load_reply_form(mainAnswerContainer)

                                // Scroll to new comment smoothly
                                setTimeout(() => {
                                    newComment[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' })
                                    // Remove animation class after animation completes
                                    setTimeout(() => newComment.removeClass('message-sent'), 500)
                                }, 100)
                            } else {
                                // Error - show toast notification
                                this.showToast('error', "Erreur lors de l'envoi du commentaire")

                                // Replace form content with error form
                                postCommentContainer.html(response.post)

                                // Re-initialize form handlers and page events
                                window.App.mount(postCommentContainer[0])
                                this.init_new_comment(postCommentContainer)
                            }
                        })
                        .fail(() => {
                            this.showToast('error', 'Une erreur est survenue')
                        })
                        .always(() => {
                            resetButtons(comment)
                            tempComment.remove()
                        })

                    return false
                })
            })
    }
}
