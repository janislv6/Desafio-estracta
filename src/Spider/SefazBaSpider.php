<?php

declare(strict_types=1);

namespace ProvaSpider\Spider;

use LogicException;
use ProvaSpider\Contract\Spider;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Consulta;
use ProvaSpider\Exception\ConsultaException;


final class SefazBaSpider implements Spider
{
    public const URL_GET = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/consultaBa.asp';
    public const URL_POST = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/result.asp';

    public function consultar(Consulta $consulta): CadastroContribuinte
    {
        $html = $this->obterResposta($consulta);
       

        if (preg_match('#<b>Cnpj\s*incorreto</b>\s*-\s*d.{1,8}gito\s*verificador\s*!</td>#is', $html)) {
            throw new ConsultaException('Cnpj incorreto - dígito verificador!');
        }

        if (preg_match('#Nenhum\s*registro\s*encontrado\s*!#is', $html)) {
            throw new ConsultaException('Nenhum registro encontrado!');
        }


        $parsed = $this->parseResposta($html);

        return $parsed;
    }

    private function obterResposta(Consulta $consulta): string
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new ConsultaException('Não foi possível iniciar o cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ]);

        try {
            curl_setopt($curl, CURLOPT_URL, self::URL_GET);
            curl_setopt($curl, CURLOPT_HTTPGET, true);

            $paginaInicial = curl_exec($curl);

            if ($paginaInicial === false) {
                throw new ConsultaException('Erro ao acessar a página inicial: ' . curl_error($curl));
            }

            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($statusCode !== 200) {
                throw new ConsultaException("A página inicial retornou o status HTTP {$statusCode}.");
            }

            $dados = [
                'sefp' => '1',
                'estado' => 'BA',
                'CGC' => $consulta->cnpj,
                'B1' => 'CNPJ  ->',
                'CPF' => '',
                'IE' => '',
            ];
            $dados = http_build_query($dados);

            curl_setopt_array($curl, [
                CURLOPT_URL => self::URL_POST,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $dados,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Referer: ' . self::URL_GET,
                ],
            ]);

            $resposta = curl_exec($curl);

            if ($resposta === false) {
                throw new ConsultaException('Erro ao realizar a consulta: ' . curl_error($curl));
            }

            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($statusCode !== 200) {
                throw new ConsultaException("A SEFAZ retornou o status HTTP {$statusCode}.");
            }

            return $resposta;

        } finally {
            curl_close($curl);
        }
    }

    public function parseResposta(string $html): CadastroContribuinte
    {
        $html = preg_replace('#&nbsp;#is', '', $html);
    
        $inscricaoEstadual = $this->getInscricaoEstadual($html);
        $cnpj = $this->getCnpj($html);
        $razaoSocial = $this->getRazaoSocial($html);
        $nomeFantasia = $this->getNomeFantasia($html);
        $situacaoCadastral = $this->getSituacaoCadastral($html);
        $endereco = $this->getEndereco($html);
        $atividadeEconomica = $this->getAtividadeEconomica($html);
        $regimeApuracao = $this->getRegimeApuracao($html);

        return new CadastroContribuinte(
            $inscricaoEstadual,
            $cnpj,
            $razaoSocial,
            $nomeFantasia,
            $situacaoCadastral,
            $endereco,
            $atividadeEconomica,
            $regimeApuracao
        );
    }

    private function getInscricaoEstadual(string $html): ?string
    {
        if (!preg_match('#Inscri.{1,8}.{1,8}o\s*Estadual:\s*</b>(\d{3}.\d{3}.\d{3})#is', $html, $match)) {
            return null;
        }
        return $match[1];
    }

    private function getCnpj(string $html): ?string
    {
        if (!preg_match('#CNPJ:</b>(\d{2}.\d{3}.\d{3}/\d{4}-\d{2})</td>#is', $html, $match)) {
            return null;
        }
        return $match[1];
    }

    private function getRazaoSocial(string $html): ?string
    {
        if (!preg_match('#Raz.{1,8}o\s*Social:</b>([^<]*)</td>#is', $html, $match)) {
            return null;
        }
        return trim($match[1]);
    }

    private function getNomeFantasia(string $html): ?string
    {
        if (!preg_match('#Nome\s*Fantasia:</b>([^<]*)</td>#is', $html, $match)) {
            return null;
        }
        return trim($match[1]);
    }

    private function getSituacaoCadastral(string $html): ?string
    {
        if (!preg_match('#Situa.{1,8}.{1,8}o\s*Cadastral\s*Vigente:</b>([^<]*)</td>#is', $html, $match)) {
            return null;
        }
        return trim($match[1]);
    }

    private function getEndereco(string $html): ?string
    {
        $logradouro = '';
        if (preg_match('#Logradouro:</b>([^<]*)</td>#is', $html, $match)) {
            $logradouro = trim(preg_replace('#\s+#is', ' ', $match[1])) . ",";
        }
        
        $numero = '';
        if (preg_match('#N.{1,8}mero:</b>([^<]*)</td>#is', $html, $match)) {
            $numero = trim($match[1]) . ",";
        }
        
        $complemento = '';
        if (preg_match('#Complemento:</b>([^<]*)</td>#is', $html, $match)) {
            $complemento = trim($match[1]) . ",";
        }
        
        $bairro = '';
        if (preg_match('#Bairro/Distrito:</b>([^<]*)</td>#is', $html, $match)) {
            $bairro = trim($match[1]) . ",";
        }

        $municipio = '';
        if (preg_match('#Munic.{1,8}pio:</b>([^<]*)</td>#is', $html, $match)) {
            $municipio = trim($match[1]) . ",";
        }

        $uf = '';
        if (preg_match('#UF:</b>([^<]*)</td>#is', $html, $match)) {
            $uf = trim($match[1]) . ",";
        }

        $cep = '';
        if (preg_match('#CEP:</b>([^<]*)</td>#is', $html, $match)) {
            $cep = "CEP: " . trim($match[1]);
        }

        if ($logradouro == '' && $numero == '' && $complemento == '' && $bairro == '' && $municipio == '' && $cep == '' && $uf == '') {
            return null;
        }

        $endereco = "{$logradouro} {$numero} {$complemento} {$bairro} {$municipio} {$uf} {$cep}";

        return $endereco;
    }

    private function getAtividadeEconomica(string $html): ?string
    {
        if (!preg_match('#Atividade\s*Econ.{1,8}mica\s*Principal:</b></td>\s*</tr>\s*<tr>\s*<td\s*colspan="5">([^<]*)</td>#is', $html, $match)) {
            return null;
        }
        return trim($match[1]);
    }

    private function getRegimeApuracao(string $html): ?string
    {
        if (!preg_match('#Condi.{1,8}.{1,8}o:</b>([^<]*)</td>#is', $html, $match)) {
            return null;
        }
        return trim($match[1]);
    }
}
