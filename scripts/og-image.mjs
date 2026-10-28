/*
 * Renders the social images of the site from design/og/event.html with headless Chrome: the image of every page
 * without one of its own (assets/images/og-image.jpg) and the fixed layer of the events' cards (assets/og/event.png,
 * which App\Picture\EventCard draws on). Run `pnpm og-image` after changing the template, and commit both.
 * Chrome is looked up at its macOS location; set CHROME_PATH to use another binary.
 */
import { execFileSync } from 'node:child_process'
import { mkdirSync, statSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const rootDir = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const template = pathToFileURL(resolve(rootDir, 'design/og/event.html')).href
const chrome = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'

const images = [
    { output: 'assets/images/og-image.jpg', layer: 'default' },
    { output: 'assets/og/event.png', layer: 'event' },
]

for (const { output, layer } of images) {
    const path = resolve(rootDir, output)
    mkdirSync(dirname(path), { recursive: true })
    execFileSync(
        chrome,
        [
            '--headless',
            '--hide-scrollbars',
            // The template loads the font and the logo from the repository through file:// URLs
            '--allow-file-access-from-files',
            '--force-device-scale-factor=1',
            '--window-size=1200,630',
            // Leaves time for the web font to load before the capture
            '--virtual-time-budget=3000',
            `--screenshot=${path}`,
            `${template}?layer=${layer}`,
        ],
        { stdio: 'ignore' }
    )
    console.log(`${output}: ${(statSync(path).size / 1024).toFixed(1)} KB`)
}
