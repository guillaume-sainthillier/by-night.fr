/**
 * The site's AJAX requests, over fetch(), with what jQuery's did for them:
 * - `X-Requested-With: XMLHttpRequest`, which the server reads to answer a fragment instead of a page
 *   (SearchController) and varies its cache on (WidgetsController)
 * - an answer outside 2xx is a failure (fetch() resolves it), carrying its JSON body: the violations of a 422, the
 *   detail of a 429
 *
 * A request that gets no answer (offline, blocked) rejects with fetch()'s TypeError, whose `status` is taken as 0
 * (see `statusOf()`).
 */

/** An answer outside 2xx */
export class HttpError extends Error {
    constructor(
        readonly status: number,
        /** The answer's body when it is JSON, else undefined */
        readonly body: unknown
    ) {
        super(`HTTP ${status}`)
        this.name = 'HttpError'
    }
}

/** The HTTP status a request failed with: 0 when it got no answer */
export function statusOf(error: unknown): number {
    return error instanceof HttpError ? error.status : 0
}

/** The JSON body of a failed request, if it had one, as the server describes it (unchecked) */
export function errorBody<T extends object>(error: unknown): Partial<T> | undefined {
    if (!(error instanceof HttpError) || null === error.body || 'object' !== typeof error.body) {
        return undefined
    }

    return error.body as Partial<T>
}

const isJson = (response: Response) => /[/+]json\b/.test(response.headers.get('Content-Type') ?? '')

async function request(url: string, init: RequestInit = {}): Promise<Response> {
    const headers = new Headers(init.headers)
    headers.set('X-Requested-With', 'XMLHttpRequest')

    const response = await fetch(url, { ...init, headers })
    if (!response.ok) {
        throw new HttpError(
            response.status,
            isJson(response) ? await response.json().catch(() => undefined) : undefined
        )
    }

    return response
}

/** The body of a successful answer: parsed when it is JSON, null when there is none (204) or it is not JSON */
async function readJson<T>(response: Response): Promise<T | null> {
    return isJson(response) ? response.json() : null
}

/** GETs an HTML fragment */
export async function getHtml(url: string): Promise<string> {
    return (await request(url)).text()
}

/** GETs JSON, with `params` added to the URL's query */
export async function getJson<T>(
    url: string,
    { params, accept = 'application/json' }: { params?: Record<string, string>; accept?: string } = {}
): Promise<T> {
    const target = new URL(url, window.location.href)
    for (const [name, value] of Object.entries(params ?? {})) {
        target.searchParams.set(name, value)
    }

    return (await request(target.href, { headers: { Accept: accept } })).json()
}

/** Sends `data` as a JSON body */
export async function sendJson<T = unknown>(method: 'POST' | 'PUT', url: string, data: unknown): Promise<T | null> {
    const response = await request(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    })

    return readJson<T>(response)
}

/** POSTs a form's fields urlencoded, as jQuery's serialize() did (files left out), or nothing at all */
export async function postForm<T = unknown>(url: string, form?: HTMLFormElement): Promise<T | null> {
    let body: URLSearchParams | undefined
    if (form) {
        body = new URLSearchParams()
        for (const [name, value] of new FormData(form)) {
            if ('string' === typeof value) {
                body.append(name, value)
            }
        }
    }
    const response = await request(url, { method: 'POST', body })

    return readJson<T>(response)
}
