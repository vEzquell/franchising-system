<script setup>
import { computed, nextTick, onBeforeUnmount, ref, shallowRef, watch } from 'vue'
import { X } from 'lucide-vue-next'
import { createScanPdf, scanPaperSizes } from './scan-pdf'
const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ remaining: { type: Number, default: 10 }, queueError: String })
const emit = defineEmits(['scanned'])
const dialog = ref(null)
const reviewHeading = ref(null)
const scanButton = ref(null)
const devices = ref([])
const selected = ref('')
const source = ref('flatbed')
const paperSize = ref('legal')
const availablePaperSizes = ref([])
const feederPages = ref(1)
const maxFeederPages = 30
const deviceLabels = { 'windows-native': 'Windows native', twain: 'Epson/TWAIN', wia: 'Windows WIA' }
const sourceLabels = { flatbed: 'Flatbed', feeder: 'Document feeder (ADF)' }
const selectedDevice = computed(() => devices.value.find(device => device.id === selected.value))
const availableSources = computed(() => selectedDevice.value ? selectedDevice.value.sources ?? ['flatbed'] : [])
const requestedPages = computed(() => source.value === 'feeder' ? feederPages.value : 1)
const validPageCount = computed(() => Number.isInteger(requestedPages.value) && requestedPages.value >= 1 && requestedPages.value <= maxFeederPages)
const loading = ref(false)
const scanning = ref(false)
const preparing = ref(false)
const pendingScan = shallowRef(null)
const previewPage = ref(0)
const errorMessage = ref('')
const connected = ref(false)
const origin = window.location.origin
const command = computed(() => `powershell.exe -STA -NoProfile -ExecutionPolicy Bypass -File tools/scanner/bridge.ps1 -AllowedOrigin "${origin}"`)
let token
let request
let session = 0
async function call(path, body) {
    const current = new AbortController()
    request = current
    const timer = setTimeout(() => current.abort(), path === '/scan' ? 600000 : 15000)
    try {
        const response = await fetch(`http://127.0.0.1:8765${path}`, {
            method: body ? 'POST' : 'GET', mode: 'cors', signal: current.signal,
            headers: { ...(token ? { 'X-Scanner-Token': token } : {}), ...(body ? { 'Content-Type': 'application/json' } : {}) },
            ...(body ? { body: JSON.stringify(body) } : {}),
        })
        const data = await response.json()
        if (!response.ok) {
            const error = new Error(data.error || 'Scanner request failed.')
            error.status = response.status
            throw error
        }
        return data
    } finally { clearTimeout(timer) }
}
async function discover() {
    const currentSession = session
    loading.value = true
    errorMessage.value = ''
    connected.value = false
    devices.value = []
    selected.value = ''
    source.value = 'flatbed'
    feederPages.value = 1
    try {
        token = null
        const { token: scannerToken } = await call('/session')
        if (session !== currentSession) return
        token = scannerToken
        const data = await call('/devices')
        if (session !== currentSession) return
        devices.value = data.devices
        availablePaperSizes.value = (data.paper_sizes ?? []).filter(size => Object.hasOwn(scanPaperSizes, size))
        if (!availablePaperSizes.value.includes(paperSize.value)) paperSize.value = 'auto'
        const preferredDevice = devices.value.find(device => device.sources?.includes('feeder')) ?? devices.value[0]
        selected.value = preferredDevice?.id ?? ''
        connected.value = true
        if (!devices.value.length) errorMessage.value = 'No Windows scanners found. Connect the scanner, install its WIA driver, and refresh.'
    } catch (error) {
        if (session !== currentSession) return
        errorMessage.value = error.status
            ? error.message
            : 'Cannot connect to the Windows scanner bridge. Start it using the command below, allow local-network access if your browser asks, then refresh.'
    }
    finally { if (session === currentSession) loading.value = false }
}
async function scan() {
    if (scanning.value || pendingScan.value || loading.value || props.remaining < 1 || !availableSources.value.includes(source.value) || !validPageCount.value) return
    const currentSession = session
    scanning.value = true
    errorMessage.value = ''
    try {
        const scanSource = source.value
        const pages = requestedPages.value
        const size = paperSize.value
        const data = await call('/scan', { device_id: selected.value, source: scanSource, pages, paper_size: size })
        if (session !== currentSession || !open.value) return
        const scannedPages = Array.isArray(data.pages) ? data.pages.length : 0
        if (scannedPages !== pages || data.complete === false) {
            throw new Error(scanSource === 'feeder'
                ? `The feeder returned ${scannedPages} of ${pages} requested pages. Reload the document and try again.`
                : 'The scanner did not return one page. Check the flatbed and try again.')
        }
        previewPage.value = 0
        pendingScan.value = { pages: data.pages, paperSize: size }
        await nextTick()
        reviewHeading.value?.focus()
    } catch (error) {
        if (session === currentSession && open.value) {
            errorMessage.value = error.name === 'AbortError' ? 'Scanning timed out. Check the scanner before trying again.' : error.message || 'Scanning failed.'
        }
    } finally {
        if (session === currentSession) scanning.value = false
    }
}

async function acceptScan() {
    const scan = pendingScan.value
    if (!scan || preparing.value || props.remaining < 1) return
    const currentSession = session

    preparing.value = true
    errorMessage.value = ''
    try {
        const file = await createScanPdf(scan.pages, scan.paperSize)
        if (!open.value || pendingScan.value !== scan) return

        emit('scanned', [file])
        await nextTick()
        if (pendingScan.value === scan && !props.queueError) {
            pendingScan.value = null
            await nextTick()
            scanButton.value?.focus()
        }
    } catch (error) {
        if (pendingScan.value === scan) errorMessage.value = error.message || 'Unable to prepare the scanned PDF.'
    } finally {
        if (session === currentSession) preparing.value = false
    }
}

async function retryScan() {
    if (preparing.value) return

    pendingScan.value = null
    errorMessage.value = ''
    await nextTick()
    scanButton.value?.focus()
}

function close() { if (!scanning.value && !preparing.value) open.value = false }
function clearScan() {
    session++
    request?.abort()
    pendingScan.value = null
    scanning.value = false
    preparing.value = false
}
watch(availableSources, sources => {
    if (!sources.includes(source.value)) source.value = sources[0] ?? 'flatbed'
})
watch(open, value => {
    if (value) { dialog.value.showModal(); discover() }
    else { clearScan(); dialog.value.close() }
})
onBeforeUnmount(clearScan)
</script>
<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="scanner-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false">
            <div class="mb-4 flex items-center justify-between"><h2 id="scanner-title" class="text-lg font-bold text-[#0D47A1]">Scan documents</h2><button type="button" aria-label="Close scanner" :disabled="scanning || preparing" class="rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="close"><X class="h-5 w-5" /></button></div>
            <p v-if="queueError" role="alert" class="mb-3 text-sm text-red-600">{{ queueError }}</p>
            <p v-if="loading" role="status" class="text-sm text-gray-500">Finding installed Windows scanners...</p>
            <p v-if="errorMessage" role="alert" class="mb-3 text-sm text-red-600">{{ errorMessage }}</p>
            <section v-if="pendingScan" class="space-y-3" aria-labelledby="scan-review-title">
                <h3 id="scan-review-title" ref="reviewHeading" tabindex="-1" class="font-semibold text-[#0D47A1]">Review scan</h3>
                <p class="text-sm text-gray-600">Is the scan clear and complete? Use it or retry to scan the document again.</p>
                <div class="flex min-h-32 items-center justify-center rounded-lg border border-blue-100 bg-slate-50 p-2">
                    <img :src="`data:image/jpeg;base64,${pendingScan.pages[previewPage].data}`" :alt="`Scanned page ${previewPage + 1} of ${pendingScan.pages.length}`" class="max-h-[55dvh] max-w-full object-contain" />
                </div>
                <div v-if="pendingScan.pages.length > 1" class="flex items-center justify-between gap-2 text-sm">
                    <button type="button" :disabled="previewPage === 0" class="rounded-lg border px-3 py-1.5 disabled:opacity-50" @click="previewPage--">Previous page</button>
                    <span aria-live="polite">Page {{ previewPage + 1 }} of {{ pendingScan.pages.length }}</span>
                    <button type="button" :disabled="previewPage === pendingScan.pages.length - 1" class="rounded-lg border px-3 py-1.5 disabled:opacity-50" @click="previewPage++">Next page</button>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" :disabled="preparing" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="retryScan">Retry scan</button>
                    <button type="button" :disabled="preparing || remaining < 1" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="acceptScan">{{ preparing ? 'Preparing PDF...' : 'Use scan' }}</button>
                </div>
            </section>
            <template v-else>
                <div v-if="!connected" class="mb-4 rounded-lg bg-blue-50 p-3 text-sm"><p>On this Windows PC, run this command from the project folder:</p><code class="mt-2 block break-all text-xs">{{ command }}</code><p class="mt-2 text-xs">Keep the bridge running while scanning. Other PCs need their own local bridge.</p></div>
                <fieldset :disabled="loading || scanning" class="space-y-4">
                    <div>
                        <label for="scanner-device" class="mb-2 block text-sm">Scanner</label>
                        <select id="scanner-device" v-model="selected" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm">
                            <option value="" disabled>Select a scanner</option>
                            <option v-for="device in devices" :key="device.id" :value="device.id">
                                {{ device.name }} ({{ deviceLabels[device.backend] ?? 'scanner' }}{{ device.sources?.includes('feeder') ? ', feeder available' : '' }})
                            </option>
                        </select>
                    </div>
                    <div v-if="availableSources.length">
                        <label for="scanner-source" class="mb-2 block text-sm">Paper source</label>
                        <select id="scanner-source" v-model="source" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm">
                            <option v-for="option in availableSources" :key="option" :value="option">{{ sourceLabels[option] }}</option>
                        </select>
                    </div>
                    <div v-if="availableSources.length && availablePaperSizes.length">
                        <label for="scanner-paper-size" class="mb-2 block text-sm">Paper size</label>
                        <select id="scanner-paper-size" v-model="paperSize" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm">
                            <option v-for="size in availablePaperSizes" :key="size" :value="size">{{ scanPaperSizes[size].label }}</option>
                        </select>
                    </div>
                    <div v-if="source === 'feeder'">
                        <label for="scanner-pages" class="mb-2 block text-sm">Number of pages</label>
                        <input id="scanner-pages" v-model="feederPages" type="number" min="1" :max="maxFeederPages" step="1" required aria-describedby="scanner-pages-help" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm" />
                        <p id="scanner-pages-help" class="mt-2 text-xs text-gray-500">Load only the pages for this document and enter the page count (1–{{ maxFeederPages }}).</p>
                    </div>
                    <button type="button" class="text-sm text-[#0D47A1] underline" @click="discover">Refresh scanners</button>
                </fieldset>
                <p class="mt-3 text-xs text-gray-500">Each accepted scan creates one PDF attachment.</p>
                <p v-if="scanning" role="status" class="mt-3 text-sm text-blue-700">Scanning... Please wait for the scanner to finish.</p>
                <div class="mt-4 flex justify-end gap-3"><button type="button" :disabled="scanning" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="close">Done</button><button ref="scanButton" type="button" :disabled="scanning || loading || !availableSources.length || !validPageCount || remaining < 1" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="scan">{{ scanning ? 'Scanning...' : 'Scan' }}</button></div>
            </template>
        </dialog>
    </Teleport>
</template>
