<?php

class DespesaRecorrenteService
{
    public static function gerarDespesas()
    {
        TTransaction::open('bolso');

        $repo = new TRepository('DespesaRecorrente');
        $criteria = new TCriteria;

        $modelos = $repo->load($criteria);
        $mesAtual = date('m');
        $anoAtual = date('Y');

        foreach ($modelos as $modelo)
        {
            // Pega o dia e hora originais do modelo
            $dia  = date('d', strtotime($modelo->data_hora));
            $hora = date('H:i:s', strtotime($modelo->data_hora));

            // Monta a nova data/hora no mês e ano atuais
            $dataLancamento = sprintf('%04d-%02d-%02d %s', $anoAtual, $mesAtual, $dia, $hora);

            if($modelo->ultima_geracao == null){
                $modelo->ultima_geracao = date('yyyy-mm-dd');
            }
            // compara mês/ano da ultima geração com o do lançamento
            $ultimaGeracao = date('Ym', strtotime($modelo->ultima_geracao));
            $mesAnoLancamento = date('Ym', strtotime($dataLancamento));

            if ($ultimaGeracao == $mesAnoLancamento) {
                continue; // já gerou despesa para esse mês/ano
            }

            $despesa = new Despesa;
            $despesa->valor        = $modelo->valor;
            $despesa->categoria_id = $modelo->categoria_id;
            $despesa->descricao    = $modelo->descricao;
            $despesa->banco_id     = $modelo->banco_id;
            $despesa->usuario_id   = $modelo->usuario_id;
            $despesa->data_hora    = $dataLancamento;
            $despesa->store();

            // Atualiza ultima_geracao
            $modelo->ultima_geracao = $dataLancamento;
            $modelo->store();
        }

        TTransaction::close();
    }
}
