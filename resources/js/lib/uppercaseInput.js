export function uppercaseInput(event) {
    const element = event.currentTarget;

    if (event.isComposing || element.composing || !['text', 'textarea'].includes(element.type) || element.hasAttribute('data-preserve-case')) {
        return;
    }

    const { value, maxLength, selectionStart, selectionEnd, selectionDirection } = element;
    const uppercase = value.toUpperCase();

    if (value === uppercase || (maxLength >= 0 && uppercase.length > maxLength)) {
        return;
    }

    element.value = uppercase;
    element.setSelectionRange(
        value.slice(0, selectionStart).toUpperCase().length,
        value.slice(0, selectionEnd).toUpperCase().length,
        selectionDirection,
    );
}
