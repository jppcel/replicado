<?php

namespace Uspdev\Replicado;

class Posgraduacao
{
    // verifica se aluno (codpes) tem matrícula ativa na pós-graduação da unidade
    public static function verifica($codpes, $codundclgi)
    {
        $query = " SELECT * FROM LOCALIZAPESSOA WHERE codpes = :codpes";
        $param = [
            'codpes' => $codpes,
        ];
        $result = DB::fetchAll($query, $param);

        foreach ($result as $row) {
            if (trim($row['tipvin']) == 'ALUNOPOS' && trim($row['sitatl']) == 'A' && trim($row['codundclg']) == $codundclgi) {
                return true;
            }

        }
        return false;
    }

    public static function ativos($codundclgi)
    {
        $query = "SELECT LOCALIZAPESSOA.*, PESSOA.* FROM LOCALIZAPESSOA";
        $query .= " INNER JOIN PESSOA ON (LOCALIZAPESSOA.codpes = PESSOA.codpes)";
        $query .= " WHERE LOCALIZAPESSOA.tipvin = 'ALUNOPOS' AND LOCALIZAPESSOA.codundclg = :codundclgi AND LOCALIZAPESSOA.sitatl = 'A'";
        $query .= " ORDER BY PESSOA.nompes ASC ";
        $param = [
            'codundclgi' => $codundclgi,
        ];
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    public static function programas($codundclgi, $codcur = null)
    {
        $query = "SELECT C.codcur, NC.nomcur";
        $query .= " FROM CURSO AS C";
        $query .= " INNER JOIN NOMECURSO AS NC ON C.codcur = NC.codcur";
        $query .= " WHERE (C.codclg = :codundclgi) AND (C.tipcur = 'POS') AND (C.dtainiccp IS NOT NULL) AND (NC.dtafimcur IS NULL)";
        $param = ['codundclgi' => $codundclgi];
        if (!is_null($codcur)) {
            $param['codcur'] = $codcur;
            $query .= " AND (C.codcur = :codcur)";
        }
        $query .= " ORDER BY NC.nomcur ASC ";
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    /**
     * Retorna lista dos orientadores credenciados na área de concentração (codare) do programa de pós graduação correspondente.
     * 
     * Foi desvinculado de VINCULOPESSOAUSP pois recém credenciados pode ainda não ter vinculo ???
     * Issue #137
     *
     * @param  int $codare Código da área de concentração pertencente a um programa de pós.
     *
     * @return array
     */
    public static function orientadores(int $codare)
    {
        $query = "SELECT r.codpes, MAX(r.dtavalini) AS dtavalini, MAX(p.sexpes) AS sexpes,";
        $query .= " MAX(r.dtavalfim) AS dtavalfim, MIN(r.nivare) AS nivare, MIN(p.nompes) AS nompes";
        $query .= " FROM R25CRECREDOC as r, PESSOA as p";
        $query .= " WHERE r.codpes = p.codpes";
        $query .= " AND r.codare = CONVERT(int, :codare)";
        $query .= " AND r.dtavalfim > GETDATE()";
        $query .= " GROUP BY r.codpes";
        $query .= " ORDER BY nompes ASC";

        $param = ['codare' => $codare];

        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);

        return $result;
    }

    public static function catalogoDisciplinas($codare)
    {
        $query = "SELECT DISTINCT r.sgldis, d.nomdis, r.numseqdis, r.dtaatvdis";
        $query .= " FROM R27DISMINCRE AS r, DISCIPLINA AS d";
        $query .= " WHERE d.sgldis = r.sgldis";
        $query .= " AND d.numseqdis = r.numseqdis";
        $query .= " AND r.codare = :codare";
        $query .= " AND (r.dtadtvdis IS NULL OR r.dtadtvdis > getdate())"; // não está desativado
        $query .= " AND d.dtaatvdis IS NOT NULL"; // está ativado
        $query .= " AND dateadd(yy,5,d.dtaatvdis)>=getdate()"; // disciplina mais nova que 5 anos
        $query .= " ORDER BY d.nomdis ASC";

        $param = ['codare' => $codare];

        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    public static function disciplina($sgldis)
    {
        $query = "SELECT TOP 1 * FROM DISCIPLINA";
        $query .= " WHERE sgldis = :sgldis";
        $query .= " ORDER BY numseqdis DESC";

        $param = ['sgldis' => $sgldis];

        $result = DB::fetchAll($query, $param);
        if ($result) {
            $result = Uteis::utf8_converter($result);
            $result = Uteis::trim_recursivo($result);
            return $result[0];
        } else {
            return [];
        }
    }

    /**
     * Retorna a lista de disciplinas em oferecimento de uma determinada área de concentração.
     *
     * Se $data não for informado pega a data corrente. Se for informado pegará as disciplinas oferecidas
     * no semestre que contém a data.
     *
     * @param int $codare Código da áreada PG.
     * @param string $data (opcional) Data na qual vai buscar os limites do semestre.
     *
     * @return void
     */
    public static function disciplinasOferecimento(int $codare, string $data = null)
    {
        $inifim = Uteis::semestre($data);

        $query = "SELECT  e.sgldis, MAX(e.numseqdis) AS numseqdis, o.numofe, d.nomdis";
        $query .= " FROM oferecimento AS o, R27DISMINCRE AS r, espacoturma AS e, disciplina AS d";
        $query .= " WHERE e.sgldis = d.sgldis";
        $query .= " AND e.sgldis = r.sgldis";
        $query .= " AND o.sgldis = r.sgldis";
        $query .= " AND o.numseqdis = d.numseqdis";
        $query .= " AND o.dtainiofe > :dtainiofe";
        $query .= " AND o.dtafimofe < :dtafimofe";
        $query .= " AND r.codare = :codare";
        $query .= " GROUP BY e.sgldis, d.nomdis, o.numofe";
        $query .= " ORDER BY d.nomdis ASC";

        $param = [
            'codare' => $codare,
            'dtainiofe' => $inifim[0],
            'dtafimofe' => $inifim[1],
        ];

        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    /**
     * Retorna dados de um oferecimento de disciplina incluindo local e ministrante.
     *
     * Local e minsitrante podem ser mais de um então são retornados na forma de array.
     * Link exemplo do Janus: https://uspdigital.usp.br/janus/componente/disciplinasOferecidasInicial.jsf?action=4&sgldis=SHS5952&ofe=1
     *
     * @param  string $sgldis Sigla da disciplina (AAA0000)
     * @param  int $numofe Número do oferecimento
     *
     * @return array
     */
    public static function oferecimento(string $sgldis, int $numofe)
    {
        $query = "SELECT o.*, d.nomdis, d.numcretotdis
           FROM OFERECIMENTO as o, DISCIPLINA as d
           WHERE o.sgldis = d.sgldis
           AND o.numseqdis = d.numseqdis
           AND o.sgldis = :sgldis
           AND o.numofe = convert(int, :numofe)
           AND o.numseqdis = (SELECT MAX(numseqdis) FROM OFERECIMENTO WHERE sgldis = :sgldis AND numofe = convert(int, :numofe))
        ";
        $param = [
            'sgldis' => $sgldis,
            'numofe' => $numofe,
        ];

        $result = DB::fetch($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);

        $result['espacoturma'] = self::espacoturma($result['sgldis'], $result['numseqdis'], $result['numofe']);
        $result['ministrante'] = self::ministrante($result['sgldis'], $result['numseqdis'], $result['numofe']);

        // Tratamento das datas no formato d/m/Y
        $result['dtainiofe'] = Uteis::data_mes($result['dtainiofe']);
        $result['dtafimofe'] = Uteis::data_mes($result['dtafimofe']);
        $result['dtalimcan'] = Uteis::data_mes($result['dtalimcan']);
        
        // Conversão codlin para nome completo do idioma
        if (isset($result['codlinofe']) && (!empty($result['codlinofe']))) {
            $result['codlinofe'] = self::idiomaDisciplina($result['codlinofe']);
        }
        return $result;
    }

    /**
     * Retorna local e horário dos oferecimentos da disciplina.
     *
     * É usado no contexto do oferecimento.
     * O nome desse método reflete o nome da tabela no BD.
     *
     * @param  string $sgldis Sigla da disciplina
     * @param  int $numseqdis Número de sequência
     * @param  int $numofe Número do oferecimento   
     *
     * @return array
     */
    public static function espacoturma(string $sgldis, int $numseqdis, int $numofe)
    {
        $query = "SELECT *
            FROM ESPACOTURMA
            WHERE sgldis = :sgldis
            AND numseqdis = convert(int, :numseqdis)
            AND numofe = convert(int, :numofe)
        ";
        $param = [
            'sgldis' => $sgldis,
            'numseqdis' => $numseqdis,
            'numofe' => $numofe,
        ];
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        if ($result && (!empty($result))) {
            // Percorre todos os dias que a disciplina é ministrada
            foreach ($result as $key => $dados) {
                $result[$key]['diasmnofe'] = Uteis::dia_semana($dados['diasmnofe']);
                $result[$key]['horiniofe'] = Uteis::horario_formatado($dados['horiniofe']);
                $result[$key]['horfimofe'] = Uteis::horario_formatado($dados['horfimofe']);
            }
        }
        return $result;
    }

    /**
     * Retorna lista de ministrantes da disciplina.
     *
     * É usado no contexto do oferecimento.
     *
     * @param  string $sgldis
     * @param  int $numseqdis
     * @param  int $numofe
     *
     * @return array
     */
    public static function ministrante(string $sgldis, int $numseqdis, int $numofe)
    {
        $query = "SELECT r.codpes, p.nompes FROM R32TURMINDOC AS r, PESSOA AS p
        WHERE r.codpes = p.codpes
        AND sgldis = :sgldis
        AND numseqdis = convert(int, :numseqdis)
        AND numofe = convert(int, :numofe)
        ORDER BY p.nompes ASC";
        $param = [
            'sgldis' => $sgldis,
            'numseqdis' => $numseqdis,
            'numofe' => $numofe,
        ];
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

/**
 * Retorna as áreas de concentração ativas dos programas de pós-graduação da unidade.
 * Se informado o código do curso (programa), retorna apenas as áreas deste curso.
 *
 * @param int $codundclgi - código da Unidade
 * @param int $codcur - código do curso de pós-graduação
 * @return type
 *
 * por Erickson Zanon - czanon@usp.br
 */
    public static function areasProgramas(int $codundclgi, int $codcur = null)
    {
        //obtém programas
        $programas = Posgraduacao::programas($codundclgi, $codcur);
        // loop sobre programas obtendos suas áreas
        $programasAreas = array();
        foreach ($programas as $p) {
            $codcur = $p['codcur'];
            $query = "SELECT codare FROM AREA WHERE codcur = :codcur";
            $param = [
                'codcur' => $codcur,
            ];
            $codAreas = DB::fetchAll($query, $param);
            $i = 0;
            foreach ($codAreas as $a) {
                $codare = $a['codare'];

                $query = "SELECT TOP(1) N.codcur,N.codare,N.nomare "
                    . " FROM NOMEAREA as N"
                    . " INNER JOIN CREDAREA as C "
                    . " ON N.codare = C.codare"
                    . " WHERE N.codare = :codare "
                    . " AND C.dtadtvare IS NULL";

                $param = [
                    'codare' => $codare,
                ];
                $areas = DB::fetchAll($query, $param);

                if (empty($areas)) {
                    continue;
                }

                $areas = Uteis::utf8_converter($areas);
                $areas = Uteis::trim_recursivo($areas);

                $nomare = $areas[0]['nomare'];

                $programasAreas[$codcur][$i]['codare'] = $codare;
                $programasAreas[$codcur][$i]['nomare'] = $nomare;
                $i++;
            }

        }
        return $programasAreas;
    }

/**
 * Retorna os alunos de um programa (codcur) de pós 
 *  da unidade (codundclgi), 
 *  indexados pela área (codare).
 * Se codare não foi determinado, busca todas as áreas do programa.
 * 
 * @param int $codundclgi - código da unidade
 * @param int $codcur - código do curso/programa
 * @param type $codare - código da área (opcional)
 * @return array
 */    
    public static function alunosPrograma(int $codundclgi, int $codcur, int $codare = null){
        // se $codare é null, seleciona todas
        if (!$codare){
            // obtém áreas do programa
            $areasPrograma = Posgraduacao::areasProgramas($codundclgi, $codcur);
            foreach ($areasPrograma[$codcur] as $area){
                $codares[] = $area['codare'];
            }
        }
        else $codares[] = $codare;
        $alunosPrograma = array();
        // loop sobre as áreas
        foreach ($codares as $codare){
            $alunosArea = array();
            $query = "SELECT DISTINCT V.codare,V.codpes,L.nompes,V.nivpgm,L.codema, V.dtainivin
                        FROM VINCULOPESSOAUSP as V 
                        INNER JOIN LOCALIZAPESSOA as L
                        ON (V.codpes = L.codpes)
                        WHERE V.tipvin = 'ALUNOPOS' 
                         AND V.sitatl = 'A'
                         AND L.codundclg = convert(int, :codundclgi) 
                         AND V.codare = convert(int, :codare)
                        ORDER BY L.nompes ASC";
            $param = [
                        'codundclgi' => $codundclgi,
                        'codare' => $codare,
                    ];       
            $alunosArea = DB::fetchAll($query, $param);            
            $alunosArea = Uteis::utf8_converter($alunosArea);
            $alunosArea = Uteis::trim_recursivo($alunosArea);
            $alunosPrograma = array_merge($alunosPrograma,$alunosArea);
        }
        return $alunosPrograma;
    }  

  /**
     * Retorna nome completo do idioma da disciplina
     *
     * É usado no contexto do oferecimento.
     *
     * @param string $codlinofe
     *
     * @return string
     */
    public static function idiomaDisciplina($codlinofe)
    {
        if (isset($codlinofe) && (!empty($codlinofe))) {
            $query = "SELECT dsclin
                        FROM IDIOMA
                        WHERE codlin = :codlinofe";

            $param = [
                'codlinofe' => $codlinofe,
            ];

            $result = DB::fetchAll($query, $param);
            $result = Uteis::utf8_converter($result);
            $result = Uteis::trim_recursivo($result);

            // Se for encontrado o nome do idioma, retornará apenas um registro
            // então já devolve apenas o campo do nome na posição 0
            return $result[0]['dsclin'];
        }
    }
}
