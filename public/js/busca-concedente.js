document.querySelectorAll('.busca-concedente').forEach((busca) => {
    const select = document.getElementById(busca.getAttribute('aria-controls'));
    const resultado = document.getElementById(busca.getAttribute('aria-describedby'));
    const opcoes = Array.from(select.options).slice(1);
    const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    busca.addEventListener('input', () => {
        const termo = normalizar(busca.value.trim());
        const digitos = termo.replace(/\D/g, '');
        const apenasDocumento = digitos && /^[\d.\/\-\s]+$/.test(termo);
        const selecionada = select.value;
        const encontradas = opcoes.filter((opcao) => apenasDocumento
            ? opcao.textContent.replace(/\D/g, '').includes(digitos)
            : normalizar(opcao.textContent).includes(termo));
        select.replaceChildren(select.options[0], ...encontradas);
        select.value = encontradas.some((opcao) => opcao.value === selecionada) ? selecionada : '';
        resultado.textContent = termo ? `${encontradas.length} concedente(s) encontrada(s).` : '';
    });
});
