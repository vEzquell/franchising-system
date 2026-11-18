const longPaper = [612, 936] // 8.5 × 13 inches at 72 points per inch.
const margin = 24

function fitOnLongPaper(width, height) {
    const [paperWidth, paperHeight] = longPaper
    const scale = Math.min((paperWidth - 2 * margin) / width, (paperHeight - 2 * margin) / height)

    return {
        scale,
        x: (paperWidth - width * scale) / 2,
        y: (paperHeight - height * scale) / 2,
    }
}

export async function combineOwnershipDocuments(attachments, readAttachment, signal) {
    const { PDFDocument, degrees } = await import('pdf-lib')
    const pdf = await PDFDocument.create()

    for (const attachment of attachments) {
        signal.throwIfAborted()
        try {
            const bytes = await readAttachment(attachment)
            signal.throwIfAborted()

            if (attachment.mime_type === 'application/pdf') {
                const source = await PDFDocument.load(bytes)
                const pages = source.getPages()
                if (!pages.length) throw new Error('Empty PDF')
                for (const sourcePage of pages) {
                    const page = pdf.addPage(longPaper)
                    if (!sourcePage.node.Contents()) continue

                    const { x: left, y: bottom, width, height } = sourcePage.getMediaBox()
                    const embedded = await pdf.embedPage(sourcePage, { left, bottom, right: left + width, top: bottom + height })
                    const rotation = ((sourcePage.getRotation().angle % 360) + 360) % 360
                    const sideways = rotation === 90 || rotation === 270
                    const displayWidth = sideways ? height : width
                    const displayHeight = sideways ? width : height
                    const { scale, x, y } = fitOnLongPaper(displayWidth, displayHeight)
                    // PDF page rotation is clockwise, while drawPage rotation is counterclockwise.
                    page.drawPage(embedded, {
                        x: x + (rotation === 180 || rotation === 270 ? displayWidth * scale : 0),
                        y: y + (rotation === 90 || rotation === 180 ? displayHeight * scale : 0),
                        xScale: scale,
                        yScale: scale,
                        rotate: degrees(-rotation),
                    })
                }
            } else if (['image/jpeg', 'image/png'].includes(attachment.mime_type)) {
                const image = attachment.mime_type === 'image/png' ? await pdf.embedPng(bytes) : await pdf.embedJpg(bytes)
                const { scale, x, y } = fitOnLongPaper(image.width, image.height)
                const page = pdf.addPage(longPaper)
                page.drawImage(image, { x, y, ...image.scale(scale) })
            } else {
                throw new Error('Unsupported attachment format')
            }
        } catch (error) {
            signal.throwIfAborted()
            const name = attachment.document_name || attachment.original_name
            throw new Error(`Unable to include “${name}” in the PDF. Check that the original file opens and is unlocked.`, { cause: error })
        }
    }

    return new Blob([await pdf.save()], { type: 'application/pdf' })
}
