document.querySelectorAll('.select-concedente').forEach((select) => {
    const opcoes = Array.from(select.options).slice(1);
    const entrada = document.createElement('input');
    const lista = document.createElement('datalist');
    const rotulo = document.querySelector(`label[for="${select.id}"]`);

    entrada.id = `${select.id}-busca`;
    entrada.setAttribute('list', `${select.id}-lista`);
    entrada.placeholder = 'Digite ou selecione uma concedente';
    entrada.required = true;
    lista.id = `${select.id}-lista`;
    const textos = opcoes.map((opcao) => opcao.textContent.trim());
    opcoes.forEach((opcao) => {
        const texto = opcao.textContent.trim();
        if (textos.filter((outro) => outro === texto).length > 1) {
            opcao.textContent = `${texto} (código ${opcao.value})`;
        }
    });
    opcoes.forEach((opcao) => {
        const item = document.createElement('option');
        item.value = opcao.textContent.trim();
        lista.append(item);
    });
    if (select.value) entrada.value = select.selectedOptions[0].textContent.trim();
    rotulo.htmlFor = entrada.id;
    select.before(entrada, lista);
    select.hidden = true;
    select.required = false;

    function validar() {
        const correspondentes = opcoes.filter((opcao) => opcao.textContent.trim() === entrada.value.trim());
        select.value = correspondentes.length === 1 ? correspondentes[0].value : '';
        entrada.setCustomValidity(select.value ? '' : 'Selecione uma concedente da lista.');
    }
    entrada.addEventListener('input', validar);
    entrada.addEventListener('change', validar);
    select.form.addEventListener('submit', (evento) => {
        validar();
        if (!select.value) {
            evento.preventDefault();
            entrada.reportValidity();
        }
    });
});
