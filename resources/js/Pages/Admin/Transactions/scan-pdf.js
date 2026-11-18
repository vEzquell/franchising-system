export const scanPaperSizes = {
    auto: { label: 'Scanner default' },
    letter: { label: 'Letter (8.5 × 11 in)', points: [612, 792] },
    a4: { label: 'A4 (210 × 297 mm)', points: [210 * 72 / 25.4, 297 * 72 / 25.4] },
    'long-bond': { label: 'Long Bond (8.5 × 13 in)', points: [612, 936] },
    legal: { label: 'Legal (8.5 × 14 in)', points: [612, 1008] },
}

export async function createScanPdf(pages, paperSize = 'auto') {
    if (!pages.length) throw new Error('No pages were scanned. Check the scanner and try again.')

    const { PDFDocument } = await import('pdf-lib')
    const pdf = await PDFDocument.create()

    const pageSize = scanPaperSizes[paperSize]?.points
    for (const page of pages) {
        const image = await pdf.embedJpg(page.data)
        const { width, height } = pageSize ? image.scaleToFit(...pageSize) : image.scale(72 / 150)
        const [pageWidth, pageHeight] = pageSize ?? [width, height]
        pdf.addPage([pageWidth, pageHeight]).drawImage(image, {
            x: (pageWidth - width) / 2,
            y: (pageHeight - height) / 2,
            width,
            height,
        })
    }

    const bytes = await pdf.save()
    if (bytes.byteLength > 5 * 1024 * 1024) {
        throw new Error('The scanned PDF exceeds 5 MB. Scan fewer pages and try again.')
    }

    return new File([bytes], `scan-${Date.now()}.pdf`, { type: 'application/pdf' })
}
