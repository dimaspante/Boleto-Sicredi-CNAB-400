<?php
/*
 * @category   	 Finance
 * @package    	 RetornoBoleto
 * @description	 Gerador de arquivos de remessa CNAB 400 (Sicredi - 748)
 * @author     	 Dimas A. Pante <dimaspante@gmail.com>
 * @license    	 http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       	 emporioadamantis.com.br
 */

session_start();

$debug = false; //se true, nao gera o arquivo e mostra o resultado na tela
$erro = false; //captura possiveis erros e so procede caso continue false

/* ----------------------
   FUNCOES GERAIS
   ---------------------- */
 
//limita quantidade de caracteres (str - string a ser reduzida, lim - limite de caracteres)
function limit($str,$lim){
	return (strlen($str) >= $lim) ?  substr($str, 0, $lim) : $str;
}

//preenche strings (pad = quantidade desejada de caracteres, pos = "LEFT" ou vazio - preenche a partir da esquerda ou direita [padrao])
function preenche($str,$lim,$pad='',$pos=''){
	if(strlen($str) >= $lim){
		$var = limit($str, $lim);
	}else{
		$var = ($pos == "LEFT") ? str_pad($str,$lim,$pad,STR_PAD_LEFT) : str_pad($str,$lim);
	}
	return $var;
}

//remove caracteres especiais de cpf/cnpj
function cgc($str){
  	$remove = array("/",".","-",","," ");
  	if(substr($str,0,1) == "0") $str = substr($str,1);
  	return str_replace($remove,"",$str);
}

//gera strings somente com zeros ou brancos (espacos)
function complementoRegistro($int,$tipo){
  	$space = '';
  	$var = ($tipo == "zeros") ? "0" : " ";
  	for($i = 1; $i <= $int; $i++) $space .= $var;
  	return $space;
}

//quebra de linha simples
function quebra(){
  	return chr(13).chr(10);
}

//calcula o modulo de 11 para gerar o DV do nosso numero
function modulo11($num,$base=9,$r=0){
    $soma = 0;
    $fator = 2;
	
    for($i = strlen($num); $i>0; $i--){
        $numeros[$i] = substr($num,$i-1,1);
        $parcial[$i] = $numeros[$i] * $fator;
        $soma += $parcial[$i];
        if($fator == $base) $fator = 1;
        $fator++;
    }
	
    if($r == 0){
        $soma *= 10;
        $digito = $soma % 11;
    }elseif($r == 1){
	  	  $r_div = (int)($soma/11);
        $digito = ($soma - ($r_div * 11));
    }
    
	return $digito;
}

//cria o digito verificador para o nosso numero
function digitoVerificador($num){
	  $resto  = modulo11($num,9,1);
	  $digito = 11 - $resto;
    return ($digito > 9) ? 0 : $digito;
}

//cria o nosso numero a partir do byte e do token (AGENCIA, POSTO, CONTA, BYTE, TOKEN)
function nossoNumero($a,$p,$c,$b,$t){
	  $dados  = $a.$p.$c;
	  $numero = date("y").substr($b,0,1).substr($t,0,5);
	  $digito = digitoVerificador($dados.$numero);
	
	  return limit($numero.$digito,9);
}

//deixa a data com 
function dataMinificada($dt){
	  $data = explode("/",$dt);
	  $ano = (strlen($data[2]) == 2) ? $data[2] : substr($data[2],2,2);
	  $mes = preenche($data[1],2,0,"LEFT");
	  $dia = preenche($data[0],2,0,"LEFT");
	  
	  return $dia.$mes.$ano;
}

//remove acentos das strings via ascii para garantir a remocao
function semAcentos($str,$up=false){
	$str = utf8_decode($str);
	$str = strtolower($str);
	$ascii['a'] = range(224, 230);
	$ascii['e'] = range(232, 235);
	$ascii['i'] = range(236, 239);
	$ascii['o'] = array_merge(range(242, 246), array(240, 248));
	$ascii['u'] = range(249, 252);

	$ascii['b'] = array(223);
	$ascii['c'] = array(231);
	$ascii['d'] = array(208);
	$ascii['n'] = array(241);
	$ascii['y'] = array(253, 255);

	foreach ($ascii as $key=>$item) {
		$acentos = '';
		foreach ($item AS $codigo) $acentos .= chr($codigo);
		$troca[$key] = '/['.$acentos.']/i';
	}

	$str = preg_replace(array_values($troca), array_keys($troca), $str);
	$str = preg_replace("/[%?!<>ºª´`^~¨]/i", "", $str);
	$str = ($up) ? strtoupper($str) : ucwords($str);
	
	return $str;
}

/* ----------------------
   VARIAVEIS DO AMBIENTE
  ---------------------- */

$VSIS	 = "2.00"; //versao do sistema (sempre com ponto)
$DIR	 = "remessa"; //diretorio para salvar os arquivos .crm (sem barra final)
$REMESSA = $REGISTRO = 1; //valor inicial padrao da remessa e do registro (deve ser incrementado para nao gerar duas remessas iguais)

//OBS: Criamos abaixo uma consulta ficticia para demonstrar o funcionamento

$CNPJ 	  = cgc(12345678909);
$CBANCO   = "748";
$AGENCIA  = "123";
$CONTA 	  = 12345;
$CARTEIRA = "X";
$ESPECIE  = "X";
$BYTE 	  = 0;
$POSTO 	  = 0;
$ACEITE   = "S";
$TAXAB 	  = 0;
$JUROS 	  = 0;

$TOKEN = str_pad($REMESSA,5,0,STR_PAD_LEFT);

if(!$TOKEN) $erro .= "TOKEN INVALIDO\r\n";

//cria o nome do arquivo pela data
//formato - XXXXMDD (XXXX - Conta do Beneficiario, M - Mes sem zero [OUT/NOV/DEZ = O N D] / DD - Dia com zero)
$DIA = date("d");
$MMES = date("m");
$MES = date("n");
$ANO = date("Y");

$filename = $DIR."/".$CONTA;
if($MES > 9){
	$filename .= ($MES == 10) ? "O" : ($MES == 11 ? "N" : "D");
}else{
	$filename .= $MES;
}
$filename .= $DIA;
$filename .= ".crm";

if($debug) echo "<pre>$filename</pre>";

$conteudo = '';

/* ----------------------
   REGISTRO HEADER
  ---------------------- */

$conteudo .= "0"; //identificacao do registro "header"
$conteudo .= "1"; //identificacao arquivo remessa
$conteudo .= "REMESSA"; //literal remessa
$conteudo .= "01"; //codigo servico cobranca
$conteudo .= preenche("COBRANCA",15); //literal cobranca
$conteudo .= $CONTA; //conta sem DV (codigo do beneficiario)
$conteudo .= preenche($CNPJ,14,0,"LEFT"); //cnpj da empresa - limite 14
$conteudo .= complementoRegistro(31,"brancos"); //31 caracteres em branco
$conteudo .= preenche($CBANCO,3); //numero do banco - sicredi "748" - limite 3
$conteudo .= preenche("SICREDI",15); //nome do banco - literal "sicredi" - limite 15
$conteudo .= $ANO.$MMES.$DIA; //data geracao arquivo - AAAAMMDD
$conteudo .= complementoRegistro(8,"brancos"); //8 caracteres em branco
$conteudo .= preenche($REMESSA,7,0,"LEFT"); //numero da remessa (remessa anterior + 1)
$conteudo .= complementoRegistro(273,"brancos"); //273 caracteres em branco
$conteudo .= $VSIS; //versao do sistema
$conteudo .= preenche($REGISTRO,6,0,"LEFT"); //sequencial do registro - 6 caracteres
$conteudo .= quebra(); //quebra de linha

/* ----------------------
   REGISTRO DETALHE
  ---------------------- */

$i = 0;
while($seus_boletos){
	$TOKEN = str_pad(($TOKEN+1),5,0,STR_PAD_LEFT);
	
	$IDBOL = $id_boleto;
	$DATAB = dataMinificada($data);
	$VCTOB = dataMinificada($vencimento);
	
	$PRECO = $valor_total);
	$VALOR = number_format($PRECO+$TAXAB, 2, '', '');
	
  $CODC  = complementoRegistro(5,"zeros"); #para homologacao, deixar zerado
	$DOCC  = cgc($cgc_cliente);
	$NOMEC = semAcentos($nome_cliente);
	$ENDC  = semAcentos($endereco_cliente);
	$ENDC .= ($numero_cliente) ? ", ".$numero_cliente : "";
	$CEPC  = limit($cep_cliente,8);
	
	$i++; //incrementa a contagem do sequencial
	
	#REGISTRO DETALHE (OBRIGATORIO)
  
	$conteudo .= "1"; //identificacao do registro "detalhe"
	$conteudo .= "A"; //tipo de cobranca (C - sem registro / A - com registro)
	$conteudo .= $CARTEIRA; //tipo da carteira (A - simples)
	$conteudo .= "A"; //tipo de impressao (A - normal / B - carne)
	$conteudo .= complementoRegistro(12,"brancos"); //12 caracteres em branco
	$conteudo .= "A"; //tipo de moeda (A - Real)
	$conteudo .= "B"; //tipo de desconto (A - valor / B - percentual)
	$conteudo .= "B"; //tipo de juros (A - valor / B - percentual)
	$conteudo .= complementoRegistro(28,"brancos"); //28 caracteres em branco
	$conteudo .= nossoNumero($AGENCIA,$POSTO,$CONTA,$BYTE,$TOKEN); //nosso numero
	$conteudo .= complementoRegistro(6,"brancos"); //6 caracteres em branco
	$conteudo .= $ANO.$MMES.$DIA; //data da instrucao - AAAAMMDD
	$conteudo .= " "; //opcoes para vencido (instrucao = 31: A - desconto, B - juros dia, C - desconto dia antecipacao, D - dia limite desconto, E - cancelamento protesto)
	$conteudo .= "N"; //postagem do titulo direto ao pagador (Padrao: para o beneficiario)
	$conteudo .= " "; //1 caractere em branco
	$conteudo .= "B"; //emissao do titulo (A - Sicredi, B - beneficiario)
	$conteudo .= complementoRegistro(2,"brancos"); //numero da parcela do carne (se nao for carne, 2 caracteres em branco - instrucao 4)
	$conteudo .= complementoRegistro(2,"brancos"); //numero total de parcelas do carne (se nao for carne, 2 caracteres em branco - instrucao 4)
	$conteudo .= complementoRegistro(4,"brancos"); //4 caracteres em branco
	$conteudo .= complementoRegistro(10,"zeros"); //desconto por dia de antecipacao (Padrao: 10 zeros)
	$conteudo .= complementoRegistro(4,"zeros"); //% multa por pagamento em atraso, sem decimal (Padrao: 4 zeros)
	$conteudo .= complementoRegistro(12,"brancos"); //12 caracteres em branco
	$conteudo .= "01"; //instrucao (01 - cadastro de titulo)
	$conteudo .= preenche($IDBOL,10,0,"LEFT"); //seu numero (nunca pode repetir - ex: numero nf - 10 caracteres)
	$conteudo .= $VCTOB; //data de vencimento do boleto (limite 6 caracteres - Padrao: DDMMAA)
	$conteudo .= preenche($VALOR,13,0,"LEFT"); //valor do titulo (13 caracteres, sem decimal - zeros a esquerda)
	$conteudo .= complementoRegistro(9,"brancos"); //9 caracteres em branco
	$conteudo .= "A"; //especie do titulo (A - duplicata mercantil indicacao, C - nota promissoria, G - recibo, J - duplicata servico indicacao, K - outros. Padrao: A)
	$conteudo .= limit($ACEITE,1); //aceite do titulo - S / N
	$conteudo .= $DATAB; //data de emissao (limite 6 caracteres - Padrao: DDMMAA)
	$conteudo .= "00"; //protesto (00 - nao protestar, 06 - protestar automaticamente)
	$conteudo .= "00"; //dias para protesto automatico
	$conteudo .= preenche($JUROS,13,0,"LEFT"); //valor/percentual juros por dia de atraso (13 caracteres com zeros a esquerda)
	$conteudo .= complementoRegistro(6,"zeros"); //data limite desconto (6 caracteres, ou zeros)
	$conteudo .= complementoRegistro(13,"zeros"); //valor/percentual do desconto (13 caracteres com zeros a esquerda)
	$conteudo .= complementoRegistro(13,"zeros"); //13 caracteres com zeros
	$conteudo .= complementoRegistro(13,"zeros"); //valor do abatimento
	$conteudo .= (strlen($DOCC) == 11) ? 1 : 2; //tipo pessoa pagador (1 - PF, 2 - PJ), calculado atraves do comprimento da variavel $DOCC, trazida do banco
	$conteudo .= complementoRegistro(1,"zeros"); //1 caractere com zero
	$conteudo .= preenche($DOCC,14,0,"LEFT"); //cpf/cnpj do pagador
	$conteudo .= preenche($NOMEC,40); //nome do pagador - sem acentos (limite 40 caracteres)
	$conteudo .= preenche($ENDC,40); //endereco do pagador - sem acentos (limite 40 caracteres)
	$conteudo .= complementoRegistro(5,"zeros"); //codigo do pagador na cooperativa beneficiaria (zero se novo ou se beneficiario nao usa)
	$conteudo .= complementoRegistro(6,"zeros"); //6 caracteres com zeros
	$conteudo .= complementoRegistro(1,"brancos"); //1 caractere em branco
	$conteudo .= $CEPC; //cep do pagador (limite 8 caracteres)
	$conteudo .= $CODC; //codigo do pagador (zero quando inexistente - limite 5 caracteres)
	$conteudo .= complementoRegistro(14,"zeros"); //cpf/cnpj do sacador avalista (zerado caso nao haja)
	$conteudo .= complementoRegistro(41,"brancos"); //nome do sacador avalista (em branco caso nao haja)
	$conteudo .= preenche($i,6,0,"LEFT"); // numero sequencial do registro (primeiro deve ser 000002)
	$conteudo .= quebra();
	
	#REGISTRO MENSAGEM (OPCIONAL)
	$i++; //incrementa o registro
	
	$nnumero = nossoNumero($AGENCIA,$POSTO,$CONTA,$BYTE,$TOKEN);
	
	$conteudo .= "2"; //identificacao do registro "mensagem"
	$conteudo .= complementoRegistro(11,"brancos"); //11 caracteres em branco
	$conteudo .= $nnumero; //nosso numero
	$conteudo .= preenche(semAcentos($instrucoes1,true),80," "); //1a instrucao para pagamento no boleto (limite 80 caracteres sem acento)
	$conteudo .= preenche(semAcentos($instrucoes2,true),80," "); //2a instrucao para pagamento no boleto (limite 80 caracteres sem acento)
	$conteudo .= preenche(semAcentos($instrucoes3,true),80," "); //3a instrucao para pagamento no boleto (limite 80 caracteres sem acento)
	$conteudo .= preenche(semAcentos($instrucoes4,true),80," "); //4a instrucao para pagamento no boleto (limite 80 caracteres sem acento)
	$conteudo .= preenche($IDBOL,10,0,"LEFT"); //seu numero (nunca pode repetir - ex: numero nf - 10 caracteres)
	$conteudo .= complementoRegistro(43,"brancos"); //43 caracteres em branco
	$conteudo .= preenche($i,6,0,"LEFT"); //numero sequencial do registro
	$conteudo .= quebra();
}

#REGISTRO TRAILER (FECHAMENTO)

$conteudo .= "9"; //identificacao do registro "trailer"
$conteudo .= "1"; //identificacao do arquivo remessa
$conteudo .= $CBANCO; //codigo do banco (Sicredi - 748)
$conteudo .= $CONTA; //conta sem DV (codigo do beneficiario)
$conteudo .= complementoRegistro(384,"brancos"); //384 caracteres em branco
$conteudo .= preenche(($i+1),6,0,"LEFT"); //numero sequencial do registro - ultimo numero +1
$conteudo .= quebra();

/* ----------------------
   GERACAO DO ARQUIVO
  ---------------------- */

if($debug){
  echo $conteudo;
}else{
	if(!$handle = fopen($filename, 'w+')) $erro .= "ERRO AO ABRIR $filename: CONFIRA AS PERMISSOES DE LEITURA.\r\n";
	if(fwrite($handle, "$conteudo") === FALSE) $erro .= "ERRO AO ESCREVER EM $filename: CONFIRA AS PERMISSOES DE ESCRITA.\r\n";
	fclose($handle);
	
	return ($erro) ? $erro : "Arquivo de remessa gerado com sucesso!";
}
