document.querySelectorAll('.select-concedente').forEach((select) => {
    const opcoes = Array.from(select.options).slice(1);
    const controle = document.createElement('div');
    const entrada = document.createElement('input');
    const lista = document.createElement('div');
    const aviso = document.createElement('small');
    const rotulo = document.querySelector(`label[for="${select.id}"]`);
    const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    let encontradas = [];
    let indice = -1;

    controle.className = 'concedente-controle';
    entrada.id = `${select.id}-busca`;
    entrada.placeholder = 'Digite o nome ou CPF/CNPJ';
    entrada.autocomplete = 'off';
    entrada.required = true;
    entrada.setAttribute('role', 'combobox');
    entrada.setAttribute('aria-autocomplete', 'list');
    entrada.setAttribute('aria-expanded', 'false');
    entrada.setAttribute('aria-controls', `${select.id}-lista`);
    entrada.setAttribute('aria-describedby', `${select.id}-aviso`);
    lista.id = `${select.id}-lista`;
    lista.className = 'concedente-opcoes';
    lista.setAttribute('role', 'listbox');
    lista.hidden = true;
    aviso.id = `${select.id}-aviso`;
    aviso.setAttribute('aria-live', 'polite');
    rotulo.htmlFor = entrada.id;
    select.before(controle);
    controle.append(entrada, lista, aviso);
    select.hidden = true;
    select.required = false;
    if (select.value) entrada.value = select.selectedOptions[0].textContent.trim();

    function fechar() {
        lista.hidden = true;
        entrada.setAttribute('aria-expanded', 'false');
        entrada.removeAttribute('aria-activedescendant');
    }
    function escolher(opcao) {
        select.value = opcao.value;
        entrada.value = opcao.textContent.trim();
        entrada.setCustomValidity('');
        aviso.textContent = '';
        fechar();
    }
    function atualizar() {
        const termo = normalizar(entrada.value.trim());
        const digitos = termo.replace(/\D/g, '');
        const documento = digitos && /^[\d.\/\-\s]+$/.test(termo);
        encontradas = opcoes.filter((opcao) => documento
            ? opcao.textContent.replace(/\D/g, '').includes(digitos)
            : normalizar(opcao.textContent).includes(termo));
        indice = -1;
        lista.replaceChildren();
        encontradas.forEach((opcao, i) => {
            const item = document.createElement('div');
            item.id = `${lista.id}-${i}`;
            item.className = 'concedente-opcao';
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', 'false');
            item.textContent = opcao.textContent.trim();
            item.addEventListener('pointerdown', (evento) => evento.preventDefault());
            item.addEventListener('click', () => escolher(opcao));
            lista.append(item);
        });
        aviso.textContent = encontradas.length ? `${encontradas.length} concedente(s) encontrada(s).` : 'Nenhuma concedente encontrada.';
        lista.hidden = !encontradas.length;
        entrada.setAttribute('aria-expanded', String(!lista.hidden));
        entrada.removeAttribute('aria-activedescendant');
    }
    entrada.addEventListener('input', () => {
        select.value = '';
        entrada.setCustomValidity('Selecione uma concedente da lista.');
        atualizar();
    });
    entrada.addEventListener('focus', () => { if (!select.value) atualizar(); });
    entrada.addEventListener('blur', fechar);
    entrada.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') { fechar(); return; }
        if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
            evento.preventDefault();
            if (lista.hidden) atualizar();
            if (!encontradas.length) return;
            indice = evento.key === 'ArrowDown' ? Math.min(indice + 1, encontradas.length - 1) : Math.max(indice - 1, 0);
            Array.from(lista.children).forEach((item, i) => item.setAttribute('aria-selected', String(i === indice)));
            const ativo = lista.children[indice];
            entrada.setAttribute('aria-activedescendant', ativo.id);
            ativo.scrollIntoView({ block: 'nearest' });
        } else if (evento.key === 'Enter' && !lista.hidden && indice >= 0) {
            evento.preventDefault();
            escolher(encontradas[indice]);
        }
    });
    select.form.addEventListener('submit', (evento) => {
        if (!select.value) {
            evento.preventDefault();
            entrada.setCustomValidity('Selecione uma concedente da lista.');
            entrada.reportValidity();
        }
    });
});
