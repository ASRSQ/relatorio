<?php

namespace App\Services;

use App\Models\EstagioAluno;
use App\Models\EstagioRelatorio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RelatorioPdf
{
    /** Renderiza duas vezes: primeiro mede as páginas reais, depois preenche o sumário. */
    public function gerar(EstagioAluno $aluno, EstagioRelatorio $relatorio, bool $validar = true): array
    {
        try {
            [, $mapa] = $this->renderizar($aluno, $relatorio, []);
            $paginas = array_map(fn ($numeros) => min($numeros), $mapa);
            [$pdf, $medidas, $elementos] = $this->renderizar($aluno, $relatorio, $paginas);
        } catch (\Throwable $e) {
            Log::error('Falha técnica na geração do PDF.', ['tipo' => get_class($e)]);
            throw ValidationException::withMessages(['pdf' => 'Não foi possível gerar ou verificar o PDF. Tente novamente ou procure a escola.']);
        }
        $erros = [];
        $nomes = ['capa' => 'Capa', 'identificacao' => 'Identificação', 'sumario' => 'Sumário', 'introducao' => 'Introdução', 'atividades' => 'Atividades desenvolvidas', 'conclusao' => 'Conclusão e assinatura', 'referencias' => 'Referências'];
        $esperadas = ['capa', 'identificacao', 'sumario', 'introducao', 'atividades', 'conclusao'];
        $temReferencias = (bool) preg_match('/[^\s\p{Z}]/u', $relatorio->referencias ?? '');
        if ($temReferencias) {
            $nomes['conclusao'] = 'Conclusão';
            $nomes['referencias'] = 'Referências e assinatura';
            $esperadas[] = 'referencias';
        }
        $falhaTecnica = false;
        $paginasPorSecao = [];
        $secoesPorPagina = [];
        $primeiraPaginaEsperada = 1;
        foreach ($esperadas as $indice => $campo) {
            $numeros = array_keys($medidas[$campo] ?? []);
            sort($numeros);
            $paginasPorSecao[$campo] = $numeros;
            foreach ($numeros as $numero) {
                $secoesPorPagina[$numero][] = $campo;
            }
            if (! $numeros || ($paginas[$campo] ?? null) !== $numeros[0] || $numeros[0] !== $primeiraPaginaEsperada || $numeros !== range($numeros[0], end($numeros))) {
                $falhaTecnica = true;
                continue;
            }
            if (count($numeros) > 1) {
                $erros[$campo] = $nomes[$campo].': o conteúdo ultrapassa uma página. Reduza o texto ou as quebras de linha.';
                if ($campo === 'atividades' && preg_match('/\R/u', $relatorio->atividades ?? '')) {
                    try {
                        $comparacao = clone $relatorio;
                        $comparacao->atividades = preg_replace('/\R(?:\h*\R)*/u', ' ', $relatorio->atividades);
                        [, $mapaSemQuebras] = $this->renderizar($aluno, $comparacao, []);
                        if (count($mapaSemQuebras['atividades'] ?? []) === 1) {
                            $erros[$campo] = 'Atividades desenvolvidas: as quebras de linha e a separação em parágrafos fazem o texto ultrapassar uma página. Reduza as quebras de linha ou reorganize os parágrafos.';
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Não foi possível comparar as quebras de linha do PDF.', ['tipo' => get_class($e)]);
                    }
                }
            }
            $primeiraPaginaEsperada = end($numeros) + 1;
        }
        $total = $pdf->getDomPDF()->getCanvas()->get_page_count();
        if ($total !== $primeiraPaginaEsperada - 1) {
            $falhaTecnica = true;
        }
        Log::info('Diagnóstico de paginação do relatório.', [
            'paginas_geradas' => $total, 'paginas_esperadas' => count($esperadas),
            'referencias_preenchidas' => $temReferencias, 'secoes_por_pagina' => $secoesPorPagina,
            'paginas_por_secao' => $paginasPorSecao, 'secoes_excedidas' => array_keys($erros),
            'primeiros_elementos_por_pagina' => $elementos, 'falha_tecnica' => $falhaTecnica,
        ]);
        if ($falhaTecnica) {
            $erros = ['pdf' => 'Não foi possível verificar a paginação do PDF. Tente novamente ou procure a escola.'];
        }
        if ($validar && $erros) {
            throw ValidationException::withMessages($erros);
        }
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('Helvetica', 'normal');
        $canvas->page_script(function ($numero, $total, $canvas, $metrics) use ($font) {
            if ($numero > 1) {
                $texto = (string) $numero;
                $canvas->text(($canvas->get_width() - $metrics->getTextWidth($texto, $font, 10)) / 2, $canvas->get_height() - 35, $texto, $font, 10);
            }
        });

        return [$pdf, $erros];
    }

    private function renderizar(EstagioAluno $aluno, EstagioRelatorio $relatorio, array $paginas): array
    {
        $mapa = [];
        $elementos = [];
        $pdf = Pdf::loadView('pdf.estagio', compact('aluno', 'relatorio', 'paginas'))->setPaper('a4')->setOption('isRemoteEnabled', false);
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$mapa, &$elementos) {
            $node = $frame->get_node();
            if ($node->nodeType !== XML_TEXT_NODE || trim($node->nodeValue) === '') {
                return;
            }
            $elemento = $node->parentNode?->nodeName;
            for ($node = $node->parentNode; $node; $node = $node->parentNode) {
                $campo = $node->attributes?->getNamedItem('data-secao')?->nodeValue;
                if ($campo) {
                    $mapa[$campo][$canvas->get_page_number()] = $canvas->get_page_number();
                    $elementos[$campo][$canvas->get_page_number()] ??= $elemento;
                    break;
                }
                $id = $node->attributes?->getNamedItem('id')?->nodeValue ?? '';
                if (str_starts_with($id, 'secao-')) {
                    $campo = substr($id, 6);
                    $mapa[$campo][$canvas->get_page_number()] = $canvas->get_page_number();
                    $elementos[$campo][$canvas->get_page_number()] ??= $elemento;
                    break;
                }
            }
        }]]);
        $pdf->render();

        return [$pdf, $mapa, $elementos];
    }
}
