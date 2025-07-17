<?php

class Dinheiro
{
    /**
     * Converte uma string no formato brasileiro para centavos.
     * Exemplo: "R$ 1.234,56" -> 123456
     *
     * @param string $valor Valor monetário formatado
     * @return int Valor em centavos
     */
    public static function brlParaCentavos(string $valor): int
    {
        // Remove o símbolo e espaços
        $valorLimpo = trim(str_replace('R$', '', $valor));
        // Remove os pontos dos milhares e troca a vírgula pelo ponto (para conversão em float)
        $valorLimpo = str_replace(['.', ','], ['', '.'], $valorLimpo);
        // Converte para float, multiplica por 100 e retorna como inteiro
        return (int) round((float) $valorLimpo * 100);
    }

    /**
     * Formata valor em centavos para string formatada (ex: "R$ 1.234,56")
     */
    public static function formataReal($centavos)
    {
        return "R$ " . number_format($centavos / 100, 2, ',', '.');
    }

    /**
     * Formata valor em centavos para string sem "R$" (útil para formulários)
     */
    public static function formatoSimples($centavos)
    {
        $valorLimpo = preg_replace('/\D/', '', $centavos);

        return number_format($valorLimpo / 100, 2, ',', '.');
    }

    /**
     * Formata de string para centavos
     */
    public static function somenteNumeros(string $valor): int
    {
        // Remove todos os caracteres que não são números da string
        $valorLimpo = preg_replace('/\D/', '', $valor);

        // Retorna o valor como um inteiro bruto
        return (int) $valorLimpo;
    }


}
