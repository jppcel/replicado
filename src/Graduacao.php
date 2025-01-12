<?php

namespace Uspdev\Replicado;

class Graduacao
{
    public static function verifica($codpes, $codundclgi)
    {
        $query = " SELECT * FROM LOCALIZAPESSOA WHERE codpes = convert(int,:codpes)"; 
        $param = [
            'codpes' => $codpes,
        ];
        $result = DB::fetchAll($query, $param);

        if(!empty($result)) {
            foreach ($result as $row)
            {
                if (trim($row['tipvin']) == 'ALUNOGR' && 
                   trim($row['sitatl']) == 'A'  && 
                   trim($row['codundclg']) == $codundclgi) 
                   return true;
            }
        }
        return false;
    }

    /**
     * Método para retornar alunos ativos na unidade
     *
     * @param Int $condundclgi
     * @param String $partNome (optional)
     * @return array(campos tabela LOCALIZAPESSOA)
     */    public static function ativos($codundclgi, $parteNome = null)
    {
        $param = [
            'codundclgi' => $codundclgi,
        ];
        $query = " SELECT LOCALIZAPESSOA.* FROM LOCALIZAPESSOA";
        $query .= " WHERE LOCALIZAPESSOA.tipvin = 'ALUNOGR' AND LOCALIZAPESSOA.codundclg = convert(int,:codundclgi)";
        if (!is_null($parteNome)) {
            $parteNome = trim(utf8_decode(Uteis::removeAcentos($parteNome)));
            $parteNome = strtoupper(str_replace(' ','%',$parteNome));
            $query .= " AND nompesfon LIKE :parteNome";
            $param['parteNome'] = '%' . Uteis::fonetico($parteNome) . '%';
        }
        $query .= " ORDER BY nompes ASC";
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    /**
     * Método para retornar dados do curso de um aluno na unidade
     *
     * @param Int $codpes
     * @param Int $codundclgi
     * @return array(codpes, nompes, codcur, codhab, nomhab, dtainivin, codcurgrd)
     */
    public static function curso($codpes, $codundclgi)
    {
        $query = " SELECT L.codpes, L.nompes, C.codcur, C.nomcur, H.codhab, H.nomhab, V.dtainivin, V.codcurgrd";
        $query .= " FROM LOCALIZAPESSOA L";
        $query .= " INNER JOIN VINCULOPESSOAUSP V ON (L.codpes = V.codpes)";
        $query .= " INNER JOIN CURSOGR C ON (V.codcurgrd = C.codcur)";
        $query .= " INNER JOIN HABILITACAOGR H ON (H.codhab = V.codhab)";
        $query .= " WHERE (L.codpes = convert(int,:codpes))";
        $query .= " AND (L.tipvin = 'ALUNOGR' AND L.codundclg = convert(int,:codundclgi))";
        $query .= " AND (V.codcurgrd = H.codcur AND V.codhab = H.codhab)";
        $param = [
            'codpes' => $codpes,
            'codundclgi' => $codundclgi,
        ];
        $result = DB::fetch($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    public static function programa($codpes)
    {
        $query = " SELECT TOP 1 * FROM HISTPROGGR ";
        $query .= " WHERE (HISTPROGGR.codpes = convert(int,:codpes)) ";
        $query .= " AND (HISTPROGGR.stapgm = 'H' OR HISTPROGGR.stapgm = 'R') ";
        $query .= " ORDER BY HISTPROGGR.dtaoco DESC ";
        $param = [
            'codpes' => $codpes,
        ];
        $result = DB::fetch($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    public static function nomeCurso($codcur)
    {
        $query = " SELECT TOP 1 * FROM CURSOGR ";
        $query .= " WHERE (CURSOGR.codcur = convert(int, :codcur)) ";
        $param = [
            'codcur' => $codcur,
        ];
        $result = DB::fetch($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result['nomcur'];
    }

    public static function nomeHabilitacao($codhab, $codcur)
    {
        $query = " SELECT TOP 1 * FROM HABILITACAOGR ";
        $query .= " WHERE (HABILITACAOGR.codhab = convert(int, :codhab) AND HABILITACAOGR.codcur = convert(int, :codcur)) ";
        $param = [
            'codhab' => $codhab,
            'codcur' => $codcur,
        ];
        $result = DB::fetch($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result['nomhab'];
    }

    public static function obterCursosHabilitacoes($codundclgi)
    {
        $query = " SELECT CURSOGR.*, HABILITACAOGR.* FROM CURSOGR, HABILITACAOGR";
        $query .= " WHERE (CURSOGR.codclg = convert(int, :codundclgi)) AND (CURSOGR.codcur = HABILITACAOGR.codcur)";
        $query .= " AND ( (CURSOGR.dtaatvcur IS NOT NULL) AND (CURSOGR.dtadtvcur IS NULL) )";
        $query .= " AND ( (HABILITACAOGR.dtaatvhab IS NOT NULL) AND (HABILITACAOGR.dtadtvhab IS NULL) )";
        $query .= " ORDER BY CURSOGR.nomcur, HABILITACAOGR.nomhab ASC";
        $param = [
            'codundclgi' => $codundclgi,
        ];
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    /**
     * Método para obter as disciplinas de graduação oferecidas na unidade
     *
     * @param Array $arrCoddis
     * @return void
     */
    public static function obterDisciplinas($arrCoddis)
    {
        $query = " SELECT D1.* FROM DISCIPLINAGR AS D1";
        $query .= " WHERE (D1.verdis = (
            SELECT MAX(D2.verdis) FROM DISCIPLINAGR AS D2 WHERE (D2.coddis = D1.coddis) 
        )) AND ( ";
        foreach ($arrCoddis as $sgldis) {
            $query .= " (D1.coddis LIKE '$sgldis%') OR ";
        }
        $query = substr($query, 0, -3);
        $query .= " ) ";
        $query .= " ORDER BY D1.coddis ASC"; 
        $result = DB::fetchAll($query);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    /**
     * Método para trazer o nome da disciplina de graduação
     *
     * @param String $coddis
     * @return void
     */
    public static function nomeDisciplina($coddis)
    {
        $query = " SELECT D1.* FROM DISCIPLINAGR AS D1";
        $query .= " WHERE (D1.verdis = (
            SELECT MAX(D2.verdis) FROM DISCIPLINAGR AS D2 WHERE (D2.coddis = D1.coddis)
        )) AND (D1.coddis = :coddis)";
        $param = [
            'coddis' => $coddis,
        ];
        $result = DB::fetch($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result['nomdis'];
    }

    /**
     * Método para trazer as disciplinas, status e créditos concluídos
     *
     * @param Int $codpes
     * @return void
     */
    public static function disciplinasConcluidas($codpes, $codundclgi)
    {
        $programa = self::programa($codpes);
        $programa = $programa['codpgm'];
        $ingresso = self::curso($codpes, $codundclgi);
        $ingresso = substr($ingresso['dtainivin'], 0, 4);
        $query  = "SELECT DISTINCT H.coddis, H.rstfim, D.creaul FROM HISTESCOLARGR AS H, DISCIPLINAGR AS D ";
        $query .= "WHERE H.coddis = D.coddis AND H.verdis = D.verdis AND H.codpes = convert(int, :codpes) AND H.codpgm = convert(int, :programa) ";
        $query .= "AND	(H.codtur = '0' OR CONVERT(INT, CONVERT(CHAR(4), H.codtur)) >= YEAR(:ingresso)) ";
        $query .= "AND (H.rstfim = 'A' OR H.rstfim = 'D' OR (H.rstfim = NULL AND H.stamtr = 'M' AND H.codtur LIKE ':ingresso' + '1%')) ";
        $query .= "ORDER BY H.coddis";
        $param = [
            'codpes' => $codpes,
            'programa' => $programa,
            'ingresso' => $ingresso,
            'ingresso' => $ingresso,
        ];
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    /**
     * Método para trazer os créditos de uma disciplina
     *
     * @param string $coddis
     * @return int $creaul
     */
    public static function creditosDisciplina($coddis)
    {
        $query = " SELECT D1.creaul FROM DISCIPLINAGR AS D1";
        $query .= " WHERE (D1.verdis = (
            SELECT MAX(D2.verdis) FROM DISCIPLINAGR AS D2 WHERE (D2.coddis = D1.coddis)
        )) AND (D1.coddis = :coddis)";
        $param = [
            'coddis' => $coddis,
        ];
        $result = DB::fetch($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result['creaul'];
    }  

    /**
     * Créditos atribuídos por Aproveitamento de Estudos no exterior
     * Documentação da replicação: * credito-aula-atribuido 
     *                             * creaulatb
     *                             * Número de Créditos aula, atribuído pelo órgão responsável, 
     *                               a uma disciplina livre cursada no exterior por aluno da USP.
     * @param Int $codpes
     * @param Int $codundclgi
     * @return Array(coddis, creaulatb)
     */
    public static function creditosDisciplinasConcluidasAproveitamentoEstudosExterior($codpes, $codundclgi)
    {
        $programa = self::programa($codpes);
        $programa = $programa['codpgm'];
        $ingresso = self::curso($codpes, $codundclgi);
        $ingresso = substr($ingresso['dtainivin'], 0, 4);
        $query  = "SELECT DISTINCT H.coddis, R.creaulatb ";
        $query .= "FROM HISTESCOLARGR AS H, DISCIPLINAGR AS D, REQUERHISTESC AS R ";
        $query .= "WHERE H.coddis = D.coddis AND H.verdis = D.verdis AND H.codpes = convert(int, :codpes) AND H.codpgm = convert(int, :programa) ";
        $query .= "AND H.coddis = R.coddis AND H.verdis = R.verdis AND H.codtur = R.codtur AND H.codpes = R.codpes ";
        $query .= "AND (H.rstfim = 'D') AND ((R.creaulatb IS NOT NULL) OR (R.creaulatb > 0)) ";
        $query .= "ORDER BY H.coddis";
        $param = [
            'codpes' => $codpes,
            'programa' => $programa,
        ];
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    /**
     * Disciplinas (grade curricular) para um currículo atual no JúpiterWeb
     * a partir do código do curso e da habilitação
     * 
     * @param String $codcur
     * @param Int $codhab
     * @return Array(coddis, nomdis, verdis, numsemidl, tipobg)
     */
    public static function disciplinasCurriculo($codcur, $codhab)
    {
        $query = "SELECT G.coddis, D.nomdis, G.verdis, G.numsemidl, G.tipobg ";
        $query .= " FROM GRADECURRICULAR G INNER JOIN DISCIPLINAGR D ON (G.coddis = D.coddis AND G.verdis = D.verdis)";
        $query .= " WHERE G.codcrl IN (SELECT TOP 1 codcrl";
        $query .= " FROM CURRICULOGR";
        $query .= " WHERE codcur = :codcur AND codhab = convert(int, :codhab)";
        $query .= " ORDER BY dtainicrl DESC)";
        $param = [
            'codcur' => $codcur,
            'codhab' => $codhab,
        ];
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

    /**
     * Disciplinas equivalentes de um currículo atual no JúpiterWeb
     * a partir do código do curso e da habilitação
     * 
     * @param String $codcur
     * @param Int $codhab
     * @return Array(coddis, verdis, tipobg, coddis_equivalente, verdis_equivalente)
     */
    public static function disciplinasEquivalentesCurriculo($codcur, $codhab)
    {
        $query = "SELECT G.codeqv, G.coddis, G.verdis, GC.tipobg, E.coddis as coddis_eq, E.verdis as verdis_eq ";
        $query .= " FROM GRUPOEQUIVGR G INNER JOIN EQUIVALENCIAGR E ON (G.codeqv = E.codeqv) ";
        $query .= " INNER JOIN GRADECURRICULAR GC ON (GC.coddis = G.coddis AND GC.verdis = G.verdis AND G.codcrl = GC.codcrl)";
        $query .= " WHERE G.codcrl IN (SELECT TOP 1 codcrl";
        $query .= " FROM CURRICULOGR";
        $query .= " WHERE codcur = :codcur AND codhab = convert(int, :codhab)";
        $query .= " ORDER BY dtainicrl DESC)";
        $param = [
            'codcur' => $codcur,
            'codhab' => $codhab,
        ];
        $result = DB::fetchAll($query, $param);
        $result = Uteis::utf8_converter($result);
        $result = Uteis::trim_recursivo($result);
        return $result;
    }

}
