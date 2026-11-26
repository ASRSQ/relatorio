(() => {
    function iso(value) {
        const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value);
        if (!match) return null;
        const day = Number(match[1]), month = Number(match[2]), year = Number(match[3]);
        if (!year || month < 1 || month > 12) return null;
        const days = [31, (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        return day >= 1 && day <= days[month - 1] ? `${match[3]}-${match[2]}-${match[1]}` : null;
    }
    function display(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
    }
    document.querySelectorAll('input[type="date"][name]').forEach(calendar => {
        const text = document.createElement('input');
        const hidden = document.createElement('input');
        const wrapper = document.createElement('span');
        wrapper.style.cssText = 'display:flex;align-items:center;gap:6px';
        text.type = 'text'; text.dataset.dataManual = ''; text.id = calendar.id; text.required = calendar.required;
        text.inputMode = 'numeric'; text.placeholder = 'DD/MM/AAAA'; text.maxLength = 10;
        text.autocomplete = 'off'; text.value = display(calendar.value);
        text.style.minWidth = '0'; text.style.flex = '1';
        hidden.type = 'hidden'; hidden.name = calendar.name; hidden.value = calendar.value;
        calendar.removeAttribute('id'); calendar.removeAttribute('name'); calendar.required = false;
        calendar.setAttribute('aria-label', 'Selecionar data no calendário');
        const picker = document.createElement('span');
        picker.style.cssText = 'position:relative;display:inline-flex;align-items:center;justify-content:center;width:44px;min-width:44px;height:44px;border:1px solid #b8c9bd;border-radius:8px;overflow:hidden';
        picker.setAttribute('aria-hidden', 'true');
        picker.textContent = '📅';
        calendar.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;opacity:0.01;cursor:pointer';
        calendar.parentNode.insertBefore(wrapper, calendar);
        picker.removeAttribute('aria-hidden');
        picker.append(calendar);
        wrapper.append(text, picker, hidden);
        function sync() {
            const value = iso(text.value);
            text.setCustomValidity(text.value && !value ? 'Informe uma data válida no formato DD/MM/AAAA.' : '');
            hidden.value = value || (text.value ? text.value : '');
            calendar.value = value || '';
        }
        text.addEventListener('input', () => {
            const digitsBefore = text.value.slice(0, text.selectionStart).replace(/\D/g, '').length;
            const digits = text.value.replace(/\D/g, '').slice(0, 8);
            text.value = digits.slice(0, 2) + (digits.length > 2 ? '/' + digits.slice(2, 4) : '') + (digits.length > 4 ? '/' + digits.slice(4) : '');
            const position = digitsBefore + (digitsBefore > 2 ? 1 : 0) + (digitsBefore > 4 ? 1 : 0);
            text.setSelectionRange(position, position);
            sync();
        });
        calendar.addEventListener('input', () => {
            text.value = display(calendar.value); sync();
            text.dispatchEvent(new Event('input', {bubbles: true}));
        });
        sync();
    });
})();
