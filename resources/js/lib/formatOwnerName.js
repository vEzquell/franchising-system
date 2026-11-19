export function formatOwnerName(owner) {
    const middleName = owner?.middle_name?.trimStart().replace(/[.\s]+$/, '')

    return [owner?.first_name, middleName ? `${middleName}.` : null, owner?.last_name, owner?.suffix]
        .filter(Boolean)
        .join(' ')
}
