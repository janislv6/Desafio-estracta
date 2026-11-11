<?php

declare(strict_types=1);

use ProvaSpider\Spider\SefazBaSpider;

it('extrai os dados do HTML da SEFAZ BA', function () {
    $html = <<<'HTML'
    <table>
        <tr><td><b>Inscrição Estadual:</b>000.665.571</td></tr>
        <tr><td><b>CNPJ:</b>13.504.675/0001-10</td></tr>
        <tr><td><b>Razão Social:</b> EMPRESA BAIANA DE ÁGUAS E SANEAMENTO S/A EMBASA</td></tr>
        <tr><td><b>Nome Fantasia:</b> EMBASA</td></tr>
        <tr><td><b>Situação Cadastral Vigente:</b> ATIVO</td></tr>
        <tr><td><b>Logradouro:</b> AVENIDA LUIS VIANA</td></tr>
        <tr><td><b>Número:</b> 420</td></tr>
        <tr><td><b>Complemento:</b> EDIF SEDUR</td></tr>
        <tr><td><b>Bairro/Distrito:</b> CENTRO ADMINISTRATIVO DA BAHIA</td></tr>
        <tr><td><b>Município:</b> SALVADOR</td></tr>
        <tr><td><b>UF:</b> BA</td></tr>
        <tr><td><b>CEP:</b> 41745-010</td></tr>
        <tr><td><b>Atividade Econômica Principal:</b></td></tr>
        <tr><td colspan="5">3600601 - Captação, tratamento e distribuição de água</td></tr>
        <tr><td><b>Condição:</b>NORMAL</td></tr>
    </table>
    HTML;

    $spider = new SefazBaSpider();

    $resultado = $spider->parseResposta($html);

    expect($resultado->inscricaoEstadual)->toBe('000.665.571')
        ->and($resultado->cnpj)->toBe('13.504.675/0001-10')
        ->and($resultado->razaoSocial)->toBe('EMPRESA BAIANA DE ÁGUAS E SANEAMENTO S/A EMBASA')
        ->and($resultado->nomeFantasia)->toBe('EMBASA')
        ->and($resultado->situacaoCadastral)->toBe('ATIVO')
        ->and($resultado->endereco)->toBe('AVENIDA LUIS VIANA, 420, EDIF SEDUR, CENTRO ADMINISTRATIVO DA BAHIA, SALVADOR, BA, CEP: 41745-010')
        ->and($resultado->atividadeEconomica)->toBe('3600601 - Captação, tratamento e distribuição de água')
        ->and($resultado->regimeApuracao)->toBe('NORMAL');
});


it('retorna null para campos ausentes no HTML', function () {
    $html = '<table>
        <tr><td><b>CNPJ:</b>13.504.675/0001-10</td></tr>
    </table>';

    $spider = new SefazBaSpider();

    $resultado = $spider->parseResposta($html);

    expect($resultado->cnpj)->toBe('13.504.675/0001-10')
        ->and($resultado->inscricaoEstadual)->toBeNull()
        ->and($resultado->razaoSocial)->toBeNull()
        ->and($resultado->nomeFantasia)->toBeNull()
        ->and($resultado->situacaoCadastral)->toBeNull()
        ->and($resultado->endereco)->toBeNull()
        ->and($resultado->atividadeEconomica)->toBeNull()
        ->and($resultado->regimeApuracao)->toBeNull();
});



it('retorna campos ausentes quando recebe um HTML inesperado', function () {
    $html = '<html><body>Página indisponível</body></html>';

    $spider = new SefazBaSpider();

    $resultado = $spider->parseResposta($html);

    expect($resultado->inscricaoEstadual)->toBeNull()
        ->and($resultado->cnpj)->toBeNull()
        ->and($resultado->razaoSocial)->toBeNull()
        ->and($resultado->nomeFantasia)->toBeNull()
        ->and($resultado->situacaoCadastral)->toBeNull()
        ->and($resultado->endereco)->toBeNull()
        ->and($resultado->atividadeEconomica)->toBeNull()
        ->and($resultado->regimeApuracao)->toBeNull();
});

