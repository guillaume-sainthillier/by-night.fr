import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { fake } from '@/js/test/fake'
import { errorBody, getHtml, getJson, HttpError, postForm, sendJson, statusOf } from '@/js/utils/http'

const json = (body: unknown, status = 200, type = 'application/json') =>
    new Response(JSON.stringify(body), { status, headers: { 'Content-Type': type } })

let fetchMock: ReturnType<typeof vi.fn<typeof fetch>>

/** The URL and options of the only request sent */
const sent = () => {
    expect(fetchMock).toHaveBeenCalledOnce()
    const [url, init] = fetchMock.mock.calls[0]

    return { url: String(url), init: init ?? {}, headers: new Headers(init?.headers) }
}

beforeEach(() => {
    fetchMock = vi.fn<typeof fetch>()
    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('window', { location: { href: 'https://by-night.test/toulouse' } })
})

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('http', () => {
    test('says every request is an AJAX one, as jQuery did', async () => {
        fetchMock.mockResolvedValue(new Response('<li>Concert</li>'))

        expect(await getHtml('/toulouse/2')).toBe('<li>Concert</li>')
        expect(sent().headers.get('X-Requested-With')).toBe('XMLHttpRequest')
    })

    test('adds the parameters to the query of a JSON GET, and asks for the given format', async () => {
        fetchMock.mockResolvedValue(json({ member: [] }, 200, 'application/ld+json; charset=utf-8'))

        expect(
            await getJson('/api/search?page=1', { params: { q: 'jazz & blues' }, accept: 'application/ld+json' })
        ).toEqual({ member: [] })
        expect(sent().url).toBe('https://by-night.test/api/search?page=1&q=jazz+%26+blues')
        expect(sent().headers.get('Accept')).toBe('application/ld+json')
    })

    test('sends JSON, and reads the JSON answer', async () => {
        fetchMock.mockResolvedValue(json({ success: true, like: true }))

        expect(await sendJson('PUT', '/api/events/42/participer', { like: true })).toEqual({
            success: true,
            like: true,
        })
        const { init, headers } = sent()
        expect(init.method).toBe('PUT')
        expect(init.body).toBe('{"like":true}')
        expect(headers.get('Content-Type')).toBe('application/json')
    })

    test('reads an answer without a JSON body as null', async () => {
        fetchMock.mockResolvedValue(new Response(null, { status: 204 }))

        expect(await sendJson('POST', '/api/feedback', { message: 'Bravo' })).toBeNull()
    })

    test('posts the text fields of a form, urlencoded, and nothing without one', async () => {
        fetchMock.mockImplementation(async () => json({ success: true }))
        const fields = new FormData()
        fields.append('comment[message]', 'Génial !')
        fields.append('comment[_token]', 'abc')
        fields.append('poster', new Blob(['x']), 'poster.jpg')
        // Node's FormData reads no form: the form's fields are the ones built above
        vi.stubGlobal(
            'FormData',
            class {
                *[Symbol.iterator]() {
                    yield* fields
                }
            }
        )

        await postForm('/commentaires/42', fake<HTMLFormElement>({}))

        expect(sent().init.method).toBe('POST')
        expect(String(sent().init.body)).toBe('comment%5Bmessage%5D=G%C3%A9nial+%21&comment%5B_token%5D=abc')

        fetchMock.mockClear()
        await postForm('/membres/connexion/facebook/deconnexion')
        expect(sent().init.body).toBeUndefined()
    })

    test('fails on an answer outside 2xx, with its JSON body', async () => {
        fetchMock.mockResolvedValue(json({ violations: [{ message: 'Trop court' }] }, 422, 'application/problem+json'))

        const error = await sendJson('POST', '/api/feedback', { message: 'Bof' }).catch((e: unknown) => e)

        expect(error).toBeInstanceOf(HttpError)
        expect(statusOf(error)).toBe(422)
        expect(errorBody<{ violations: { message: string }[] }>(error)?.violations).toEqual([{ message: 'Trop court' }])
    })

    test('fails on an HTML error page, without a body', async () => {
        fetchMock.mockResolvedValue(new Response('<h1>Erreur</h1>', { status: 500 }))

        const error = await getHtml('/toulouse/2').catch((e: unknown) => e)

        expect(statusOf(error)).toBe(500)
        expect(errorBody(error)).toBeUndefined()
    })

    test('takes a request without an answer as status 0', async () => {
        fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))

        const error = await getHtml('/toulouse/2').catch((e: unknown) => e)

        expect(statusOf(error)).toBe(0)
        expect(errorBody(error)).toBeUndefined()
    })
})
