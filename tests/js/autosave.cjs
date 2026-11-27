const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/views/estagio-editar.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function setup() {
    const nodes = new Map();
    const fields = ['introducao', 'atividades', 'conclusao', 'local', 'data_relatorio', 'inicio', 'termino',
        'serie', 'referencias', 'empresa_endereco', 'empresa_numero', 'empresa_bairro', 'empresa_cidade', 'empresa_telefone', 'empresa_email',
        'escola_endereco', 'escola_numero', 'escola_bairro', 'escola_cidade', 'escola_telefone', 'escola_email'];
    for (const id of [...fields, 'versao', 'saveStatus', 'formRelatorio', 'concluir', 'pdfLink', 'previewLink', 'progresso', 'progressoTexto', 'contIntroducao', 'contAtividades', 'contConclusao', 'barraIntroducao', 'barraAtividades', 'barraConclusao', 'preenchimentoIntroducao', 'preenchimentoAtividades', 'preenchimentoConclusao']) {
        nodes.set(id, { value: id === 'versao' ? '0' : '', style: {}, listeners: {}, setAttribute() {}, addEventListener(name, fn) { this.listeners[name] = fn; } });
    }
    const form = nodes.get('formRelatorio');
    form.action = '/relatorio';
    form.querySelectorAll = () => fields.map(id => nodes.get(id));
    form.querySelector = () => null;
    const requests = [];
    const windowListeners = {};
    const context = vm.createContext({
        document: {getElementById: id => nodes.get(id)},
        window: {addEventListener: (name, fn) => windowListeners[name] = fn, location: {assign() {}}},
        FormData: class { constructor() { this.data = Object.fromEntries([...fields, 'versao'].map(id => [id, nodes.get(id).value])); } set(k,v) { this.data[k] = v; } },
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({data: options.body.data, resolve, reject})),
        setTimeout: () => 1, clearTimeout: () => {}, Date, Error, TypeError,
    });
    vm.runInContext(source, context);
    return {nodes, requests, context, windowListeners, edit(text) { nodes.get('introducao').value = text; nodes.get('introducao').listeners.input(); }, save() { return vm.runInContext('save()', context); }};
}
const tick = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    const app = setup();
    assert.equal(vm.runInContext("contar('  á   😀  ')", app.context), 3);
    app.nodes.get('concluir').listeners.click();
    assert.match(app.nodes.get('saveStatus').textContent, /mínimos/);
    assert.equal(app.requests.length, 0);
    app.nodes.get('inicio').value = '2026-08-19';
    app.nodes.get('termino').value = '2026-12-22';
    app.nodes.get('serie').value = '3º ano';
    app.nodes.get('empresa_endereco').value = 'Endereço informado';
    app.nodes.get('referencias').value = 'Referências do aluno';
    app.nodes.get('inicio').listeners.input();
    app.edit('Primeira versão');
    const saving = app.save();
    assert.equal(app.requests.length, 1);
    assert.equal(app.requests[0].data.inicio, '2026-08-19');
    assert.equal(app.requests[0].data.termino, '2026-12-22');
    assert.equal(app.requests[0].data.serie, '3º ano');
    assert.equal(app.requests[0].data.empresa_endereco, 'Endereço informado');
    assert.equal(app.requests[0].data.referencias, 'Referências do aluno');
    app.edit('Versão mais recente');
    assert.equal(app.requests[0].data.introducao, 'Primeira versão');
    app.requests[0].resolve({ok:true, json:async()=>({versao:1, salvo_em:new Date().toISOString()})});
    await tick();
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[1].data.introducao, 'Versão mais recente');
    assert.equal(app.requests[1].data.versao, 1);
    app.requests[1].resolve({ok:true, json:async()=>({versao:2, salvo_em:new Date().toISOString()})});
    await saving;
    assert.equal(app.nodes.get('versao').value, 2);
    let prevented = false;
    app.windowListeners.beforeunload({preventDefault(){prevented=true;}});
    assert.equal(prevented, false);

    const offline = setup(); offline.edit('Texto preservado na página');
    const failed = offline.save();
    const rejection = assert.rejects(failed, /Não foi possível salvar/);
    offline.requests[0].resolve({ok:false,status:500}); await rejection;
    assert.equal(offline.nodes.get('introducao').value, 'Texto preservado na página');
    prevented=false;
    offline.windowListeners.beforeunload({preventDefault(){prevented=true;}});
    assert.equal(prevented,true);
    const retry=offline.save();
    offline.requests[1].resolve({ok:true,json:async()=>({versao:1,salvo_em:new Date().toISOString()})}); await retry;

    const conflict = setup(); conflict.edit('Texto de outra aba');
    const conflicting=conflict.save();
    const rejected=assert.rejects(conflicting,/Outra aba/);
    conflict.requests[0].resolve({ok:false,status:409}); await rejected;
    await assert.rejects(conflict.save(), /recarregue/);
    assert.equal(conflict.requests.length,1);

    const network = setup(); network.edit('Texto sem conexão');
    const disconnected = network.save();
    const networkError = assert.rejects(disconnected, /Não foi possível conectar ao servidor/);
    network.requests[0].reject(new TypeError('Failed to fetch'));
    await networkError;
    assert.match(network.nodes.get('saveStatus').textContent, /Não foi possível conectar ao servidor/);
    assert.equal(network.nodes.get('introducao').value, 'Texto sem conexão');
    console.log('Autosave: datas, serialização, alterações durante envio, recuperação de erro, conflito e erro de rede em português passaram.');
})().catch(error => { console.error(error); process.exitCode=1; });
