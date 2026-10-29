import { renderIcon } from '@silarhi/ux-icons-js'
import type { SweetAlertIcon } from 'sweetalert2'
import { LucideLoader2Icon } from '@/js/icons'
import type ToastManager from '@/js/services/modals/ToastManager'
import { loadingButtons, resetButtons } from '@/js/utils/buttons'
import { getHtml, postForm } from '@/js/utils/http'
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
        // Iterate through comments divs (normally 1 per page)
        for (const commentsContainer of document.querySelectorAll<HTMLElement>(this.options.css_main_block_comments)) {
            window.App.mount(commentsContainer) // Bind login/register links
            this.init_new_comment(commentsContainer) // Bind new comment form submission
            this.init_load_reply_form(commentsContainer) // Bind reply links for comments
            this.init_update_reply_count(commentsContainer) // Update reply counter
            this.init_load_more_comments(commentsContainer)
        }
    }

    /** The elements of an HTML fragment, ready to be inserted */
    parse(html: string): Element[] {
        const template = document.createElement('template')
        template.innerHTML = html.trim()

        return [...template.content.children]
    }

    /**
     * Binds `handler` to the click on every element of `container` matching `selector`, once: an element already bound
     * (a block of comments bound again after a page of them was loaded) is left alone
     */
    on_click(container: ParentNode, selector: string, handler: (element: HTMLElement) => void) {
        for (const element of container.querySelectorAll<HTMLElement>(selector)) {
            if (element.dataset.commentBound) {
                continue
            }
            element.dataset.commentBound = '1'
            element.addEventListener('click', (event) => {
                event.preventDefault()
                handler(element)
            })
        }
    }

    init_load_more_comments(comments: ParentNode) {
        this.on_click(comments, this.options.css_load_more_comments, async (loadMore) => {
            const btn = loadMore.querySelector<HTMLButtonElement>('.btn')
            const parentContainer = loadMore.parentElement

            // Add spinner to button
            const originalText = btn?.innerHTML ?? ''
            if (btn) {
                btn.innerHTML = `${renderIcon(this.options.icon_spinner, { class: 'icon icon-spin' })} ${originalText}`
                btn.disabled = true
            }

            let html: string
            try {
                html = await getHtml(loadMore.dataset.url ?? '')
            } catch {
                if (btn) {
                    btn.innerHTML = originalText
                    btn.disabled = false
                }
                this.showToast('error', 'Une erreur est survenue')
                return
            }

            const items = this.parse(html)
            for (const item of items) {
                this.init_load_reply_form(item)
                this.init_load_more_comments(item)
                this.init_update_reply_count(item)
            }

            loadMore.replaceWith(...items)
            if (parentContainer) {
                this.init_load_more_comments(parentContainer)
            }
        })
    }

    init_update_reply_count(comments: ParentNode) {
        for (const element of comments.querySelectorAll<HTMLElement>(this.options.css_reply_count)) {
            const thread = element.closest<HTMLElement>(this.options.css_main_reply_block)
            if (thread) {
                this.update_reply_count(thread, element.innerHTML)
            }
        }
    }

    init_load_reply_form(comments: ParentNode) {
        this.on_click(comments, this.options.css_reply_link, async (link) => {
            const originalHtml = link.innerHTML
            link.dataset.originalHtml = originalHtml
            link.innerHTML = renderIcon(this.options.icon_spinner, { class: 'icon icon-spin' })
            const postAnswerContainer = link
                .closest(this.options.css_main_reply_block)
                ?.querySelector<HTMLElement>(this.options.css_reply_form_container) // Find the post block
            if (!postAnswerContainer) {
                return
            }

            postAnswerContainer.classList.remove('is-visible')
            let html: string
            try {
                html = await getHtml(link.dataset.url ?? '')
            } catch {
                link.innerHTML = originalHtml
                this.showToast('error', 'Une erreur est survenue')
                return
            }
            postAnswerContainer.innerHTML = html
            window.App.mount(postAnswerContainer) // Bind login/register links
            this.init_reply_form(postAnswerContainer) // Bind new reply form submission

            // Use CSS transition for smooth animation
            link.innerHTML = originalHtml
            requestAnimationFrame(() => {
                postAnswerContainer.classList.add('is-visible')
                // Focus on textarea after animation starts
                setTimeout(() => {
                    postAnswerContainer.querySelector('textarea')?.focus()
                }, 150)
            })
        })
    }

    update_reply_count(mainAnswerContainer: ParentNode, answerCount: string) {
        for (const count of mainAnswerContainer.querySelectorAll(this.options.css_reply_count)) {
            count.innerHTML = answerCount
        }
    }

    /** Hides the reply form of a thread, and gives its reply link back */
    close_reply_form(answerPostContainer: HTMLElement) {
        answerPostContainer.classList.remove('is-visible')
        const link = answerPostContainer
            .closest(this.options.css_main_reply_block)
            ?.querySelector<HTMLElement>(this.options.css_reply_link)
        if (link?.dataset.originalHtml) {
            link.innerHTML = link.dataset.originalHtml
        }
    }

    init_reply_form(answerPostContainer: HTMLElement) {
        // A form shown again after an error is bound again: its cancel button with it
        answerPostContainer.querySelector('.cancel-reply')?.addEventListener('click', (event) => {
            event.preventDefault()
            this.close_reply_form(answerPostContainer)
        })

        const form = answerPostContainer.querySelector('form')
        form?.addEventListener('submit', async (event) => {
            event.preventDefault()
            const textarea = form.querySelector('textarea')
            const mainAnswerContainer = answerPostContainer.closest<HTMLElement>(this.options.css_main_reply_block)
            if (!mainAnswerContainer) {
                return
            }
            loadingButtons(form)

            let response: ReplyResponse | null
            try {
                response = await postForm<ReplyResponse>(form.action, form)
            } catch {
                this.showToast('error', 'Une erreur est survenue')
                return
            } finally {
                resetButtons(form)
            }

            if (response?.success) {
                // Success - show toast notification
                this.showToast('success', 'Réponse envoyée avec succès !')

                // Clear textarea
                if (textarea) {
                    textarea.value = ''
                    textarea.blur()
                }

                // Create replies container if it doesn't exist
                let answerContainer = mainAnswerContainer.querySelector(this.options.css_replies_container)
                if (!answerContainer) {
                    answerContainer = document.createElement('div')
                    answerContainer.className = 'chat-bubbles replies-container mt-2 ms-5'
                    mainAnswerContainer.append(answerContainer)
                }

                // Add reply with success animation
                const [newReply] = this.parse(response.comment)
                if (newReply) {
                    newReply.classList.add('message-sent')
                    answerContainer.prepend(newReply)

                    // Scroll to new reply
                    setTimeout(() => {
                        newReply.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
                        setTimeout(() => newReply.classList.remove('message-sent'), 500)
                    }, 100)
                }

                // Hide reply form, restore reply link
                this.close_reply_form(answerPostContainer)

                // Update reply count
                this.update_reply_count(mainAnswerContainer, String(response.reply_count))
            } else if (response) {
                // Error - show toast notification
                this.showToast('error', "Erreur lors de l'envoi de la réponse")

                // Replace form content with error form
                answerPostContainer.innerHTML = response.post

                // Re-initialize form handlers and page events
                window.App.mount(answerPostContainer)
                this.init_reply_form(answerPostContainer)
            }
        })
    }

    init_new_comment(comment: HTMLElement) {
        for (const form of comment.querySelectorAll('form')) {
            const textarea = form.querySelector('textarea')

            form.addEventListener('submit', async (event) => {
                event.preventDefault()
                loadingButtons(comment)

                let response: CommentResponse | null
                try {
                    response = await postForm<CommentResponse>(form.action, form)
                } catch {
                    this.showToast('error', 'Une erreur est survenue')
                    return
                } finally {
                    resetButtons(comment)
                }

                const mainCommentsContainer = form.closest(this.options.css_main_block_comments)
                const postCommentContainer = mainCommentsContainer?.querySelector<HTMLElement>(
                    this.options.css_block_poster_comment
                )
                if (!mainCommentsContainer || !postCommentContainer || !response) {
                    return
                }

                if (response.success) {
                    // Success - show toast notification
                    this.showToast('success', 'Commentaire envoyé avec succès !')

                    // Clear textarea with animation
                    if (textarea) {
                        textarea.value = ''
                        textarea.blur()
                    }

                    // Update comment counter
                    const counter = mainCommentsContainer.querySelector(this.options.css_comments_count)
                    if (counter && response.count !== undefined) {
                        counter.textContent = plural(response.count, {
                            one: '# commentaire',
                            other: '# commentaires',
                        })
                    }

                    // Check if comments body container exists
                    let commentsBodyContainer = mainCommentsContainer.querySelector('.card-body.scrollable')

                    // If no comments container exists yet (first comment), create one above the form
                    if (!commentsBodyContainer) {
                        const body = document.createElement('div')
                        body.className = 'card-body scrollable'
                        body.style.maxHeight = '600px'
                        body.innerHTML = '<div class="chat"><div class="chat-bubbles"></div></div>'
                        postCommentContainer.before(body)
                        commentsBodyContainer = body
                    }

                    // Get the chat bubbles container
                    const commentsList = commentsBodyContainer.querySelector('.chat-bubbles')

                    // Add new comment with success animation
                    const [newComment] = this.parse(response.comment)
                    if (!commentsList || !newComment) {
                        return
                    }
                    newComment.classList.add('message-sent')
                    commentsList.prepend(newComment)

                    // Initialize listeners for the new comment
                    const mainAnswerContainer = commentsList.querySelector<HTMLElement>(this.options.css_block_comment)
                    if (mainAnswerContainer) {
                        this.update_reply_count(mainAnswerContainer, '0')
                        this.init_load_reply_form(mainAnswerContainer)
                    }

                    // Scroll to new comment smoothly
                    setTimeout(() => {
                        newComment.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
                        // Remove animation class after animation completes
                        setTimeout(() => newComment.classList.remove('message-sent'), 500)
                    }, 100)
                } else {
                    // Error - show toast notification
                    this.showToast('error', "Erreur lors de l'envoi du commentaire")

                    // Replace form content with error form
                    postCommentContainer.innerHTML = response.post

                    // Re-initialize form handlers and page events
                    window.App.mount(postCommentContainer)
                    this.init_new_comment(postCommentContainer)
                }
            })
        }
    }
}
