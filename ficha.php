<?php
##########################################################################################
# Ficha Catalográfica para Teses e Dissertações - ICMC
#
##########################################################################################
# CRÉDITOS 
# Universidade de São Paulo
# Instituto de Ciências Matemáticas e de Computação (ICMC).
# Autoria: Maria Alice Soares de Castro - STI-ICMC
# Contato: Seção Técnica de Informática - sti@icmc.usp.br 
#          Biblioteca Prof. Achille Bassi - biblio@icmc.usp.br
# Todos os códigos são livres para serem utilizados e/ou modificados, desde que seja autorizado 
# pelo autor e estes créditos sejam mantidos no início de cada código fonte.
# Proibida redistribuição dos códigos sem a prévia autorização do autor.
#
# Este aplicativo utiliza o pacote PHP Pdf, que pode ser baixado a partir de 
# http://sourceforge.net/projects/pdf-php/
#
# Os arquivos associados ao quadro de ajuda estão disponíveis em
# http://www.icmc.usp.br/~biblio/index.php?destino=ficha.php
# atualização bootstrap ti.sjc@unifesp.br
##########################################################################################


// Verifica se foi entrado um nome no formulário

/* @var $nome type */
$nome = $_POST["nome"];
//$nome = filter_var($n, FILTER_SANITIZE_STRING);
if (empty($nome)) {

// Se não houver valor para nome, apresenta o formulário para ser preenchido
?>

<html>

<head>
    <title>Ficha catalográfica</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">    
    <link href="bootstrap/css/bootstrap.css" rel="stylesheet" type="text/css">      
    <link href="bootstrap/css/min.css" rel="stylesheet" type="text/css">  
</head>

<body>

<div class="container-fluid">
<div class="row">
<div class="col-lg-8 col-lg-offset-2">
<div class="panel-body">

      <div class="header">  
	<h2>Ficha Catalográfica</h2><h4>Biblioteca - Campus São José dos Campos</h4>  
      </div>
<br/>

<form class="form-horizontal" role="form" name="ficha" method="post" action="ficha.php">
<fieldset>


<legend>Ficha Catalográfica</legend>

	<div class="form-group">
	  <label class="control-label col-sm-2" for="nome">Nome</label>  
	  <div class="col-xs-8">
	      <input id="nome" name="nome" placeholder="seu nome" class="form-control" required="" type="text">
	  </div>
	</div> 
	  
	<div class="form-group">
	  <label class="control-label col-sm-2" for="sobrenome">Sobrenome</label>  
	  <div class="col-xs-8">
	  <input id="sobrenome" name="sobrenome" placeholder="seu sobrenome" class="form-control" required="" type="text">	    
	  </div>
	</div>
	
	
	<div class="form-group">
	  <label class="control-label col-sm-2" for="titulo">Título</label>  
	  <div class="col-xs-8">
	  <input id="titulo" name="titulo" placeholder="título do trabalho" class="form-control" required="" type="text">	    
	  </div>
	</div>
	
	<div class="form-group">
	  <label class="control-label col-sm-2" for="titIngles">Título em inglês</label>  
	  <div class="col-xs-8">
	  <input id="titIngles" name="titIngles" placeholder="título do trabalho em inglês" class="form-control" required="" type="text">	    
	  </div>
	</div>
	
	<div class="form-group">
	  <label class="control-label col-sm-2" for="cutter">Código Cutter</label>  
	  <div class="col-xs-4">
	  <input id="cutter" name="cutter" placeholder="código cutter" class="form-control" required="" type="text">	  
	  </div>
	  <div class="col-xs-2">
	  <a href="http://www.davignon.qc.ca/cutter1.html" target="_blank">Ver tabela</a>
	  </div>
	</div>

	
	<div class="form-group">
	  <label class="col-sm-2 control-label" for="Trabalho">Trabalho</label>	  
	  <div class="col-xs-4"> 
		<label class="radio-inline" for="trabalho">
		  <input name="trabalho" value="Tese" type="radio">Tese</label> 
		<label class="radio-inline" for="trabalho">
		  <input name="trabalho" value="Dissertação" type="radio">Dissertação</label>
	      </div>
	</div>
    
	<div class="form-group">
	  <label class="col-md-2 control-label" for="programa">Programa</label>
	  <div class="col-md-6">
	    <select id="programa" name="programa" class="form-control">
	      <option value="Mestrado em Ciência da Computação">Mestrado em Ciência da Computação</option>
	      <option value="Mestrado em Engenharia e Ciência de Materiais">Mestrado em Engenharia e Ciência de Materiais</option>
	      <option value="Doutorado em Engenharia e Ciência de Materiais">Doutorado em Engenharia e Ciência de Materiais</option>
	      <option value="Mestrado em Biotecnologia">Mestrado em Biotecnologia</option>
	      <option value="Doutorado em Biotecnologia">Doutorado em Biotecnologia</option>
	      <option value="Mestrado em Biotecnologia">Mestrado em Biotecnologia</option>
	    </select>
	  </div>
	</div>

	<span class="label label-default">Dados do orientador</span>
	<div class="form-group">
	  <label class="control-label col-sm-2" for="nome_ori">Orientador</label>  
	  <div class="col-xs-8">
	    <input id="nome_ori" name="nome_ori" placeholder="nome do orientador" class="form-control" required="" type="text">	    
	  </div>
	</div>	
    
	<div class="form-group">
	  <label class="control-label col-sm-2" for="sobrenome_ori">Sobrenome do orientador</label>  
	  <div class="col-xs-8">
	  <input id="sobrenome_ori" name="sobrenome_ori" placeholder="sobrenome do orientador" class="form-control" required="" type="text">	    
	  <input type="checkbox" name="orientadora" value="a"> orientador(a)<br /><br />
	  </div>
	</div>
      
	<span class="label label-default">Dados do coorientador</span>
      	<div class="form-group">
	  <label class="control-label col-sm-2" for="nome_coori">Nome do coorientador</label>  
	  <div class="col-xs-8">
	  <input id="nome_coori" name="nome_coori" placeholder="nome do coorientador" class="form-control" required="" type="text">	    	  
	  </div>
	</div>	     
	
	<div class="form-group">
	  <label class="control-label col-sm-2" for="sobrenome_coori">Sobrenome do coorientador</label>  
	  <div class="col-xs-8">
	  <input id="sobrenome_coori" name="sobrenome_coori" placeholder="sobrenome do coorientador" class="form-control" required="" type="text">	    
	  <input type="checkbox" name="coorientador(a)" value="a"> coorientador(a)<br /><br />
	  </div>
	</div>      
	
	<div class="form-group">
	  <label class="control-label col-sm-2" for="ano">Ano</label>  
	  <div class="col-xs-8">
	      <input id="ano" name="ano" placeholder="ano" class="form-control" required="" type="text">
	  </div>
	</div> 	
	
	<div class="form-group">
	  <label class="control-label col-sm-2" for="pags">Nº de páginas</label>  
	  <div class="col-xs-8">
	      <input id="ano" name="pags" placeholder="número de páginas" class="form-control" required="" type="text">
	  </div>
	</div> 
	
	<br/>
	
	<span class="label label-default">Assuntos</span>
	<div class="form-group">
	  <label class="control-label col-sm-2" for="pags">Assuntos</label>  
	  <div class="col-xs-8">
	      1.<input id="assunto1" name="assunto1" class="form-control" required type="text">
	      2.<input id="assunto2" name="assunto2" class="form-control" type="text">
	      3.<input id="assunto3" name="assunto3" class="form-control" type="text">
	      4.<input id="assunto4" name="assunto4" class="form-control" type="text">
	      5.<input id="assunto5" name="assunto5" class="form-control" type="text">
	  </div>
	</div>
	
	
	
	
	<div class="alert alert-success">
		<a href="http://143.107.73.99/Vocab/Sibix652.dll" target="_blank">Consulta opcional ao Vocabulário Controlado da USP</a>
	</div>
    
    
      <button type="submit" class="btn btn-success" name="envia" value="Enviar!">Enviar</button>
      <button type="reset" class="btn btn-default" name="Limpar" value="Limpar!">Limpar</button>
    <br/>
    <br/>
    
    
    
    <div class="alert alert-info">
      <a href="#" onclick="window.open('http://www.ict.unifesp.br/ficha/creditos.html', 'Pagina', 'STATUS=NO, TOOLBAR=NO, LOCATION=NO, DIRECTORIES=NO, RESISABLE=NO, SCROLLBARS=YES, TOP=10, LEFT=10, WIDTH=770, HEIGHT=400');">
      Clique para ver os crédios</a>  
    </div>
      
    </fieldset>    
    </form>
    
    <div class="alert alert-warning" role="alert">
	<small> Dúvidas/informações biblioteca.sjc@unifesp.br.</span><br/>		
		Dúvidas/problemas no preenchimento do formulário - suporte.sjc@unifesp.br</small></span><br/>
    </div>
      

</div>
</div>
</div> 
</div> <!--   container   -->

</body>

</html>

<?
}
else
{
// há alguma informação no formulário de entrada

// carrega o pacote de geração de PDF
		
	require('pdf-php/class.ezpdf.php');
	
	$titulo_ficha = utf8_decode("Ficha catalográfica elaborada pela Biblioteca da Unifesp São José dos campos");
	$subtitulo_ficha = utf8_decode("com os dados fornecidos pelo(a) autor(a)");
	
	$nome = utf8_decode($_POST["nome"]);
	$sobrenome = utf8_decode($_POST["sobrenome"]);
	$titulo = utf8_decode($_POST["titulo"]); 
	$titIngles = utf8_decode($_POST["titIngles"]);
	$cutter = $_POST["cutter"];
	$trabalho = utf8_decode($_POST["trabalho"]);  // tese / dissertação
	$curso = utf8_decode($_POST["curso"]);  // Mestrado / Doutorado
	$programa = ($_POST["programa"]);  // Programa Matemática / CCMC
	$nome_ori = utf8_decode($_POST["nome_ori"]); // nome do orientador
	$sobrenome_ori = utf8_decode($_POST["sobrenome_ori"]); // sobrenome do orientador
	$orientadora = utf8_decode($_POST["orientadora"]); // se sexo feminino, vale "a"

	$nome_coori = utf8_decode($_POST["nome_coori"]); // nome do coorientador
	$sobrenome_coori = utf8_decode($_POST["sobrenome_coori"]); // sobrenome do coorientador
	$coorientadora = utf8_decode($_POST["coorientadora"]); // se sexo feminino, vale "a"	
	
	$ano = $_POST["ano"];
	$pags = $_POST["pags"];
	$assunto1 = utf8_decode($_POST["assunto1"]);  
	$assunto2 = utf8_decode($_POST["assunto2"]);  
	$assunto3 = utf8_decode($_POST["assunto3"]);  
	$assunto4 = utf8_decode($_POST["assunto4"]);  
	$assunto5 = utf8_decode($_POST["assunto5"]);  

	$codigo1 = substr($sobrenome,0,1);
	
	// separa o título por espaços em branco e verifica a primeira palavra
	// se a primeira palavra for uma stopword, o $codigo2 será a primeira letra da segunda palavra do título
	
	$vetitulo = explode (" ",$titulo);
	
	$stopwords = array ("o", "a", "os", "as", "um", "uns", "uma", "umas", "de", "do", "da", "dos", "das", "no", "na", "nos", "nas", "ao", "aos", "à", "às", "pelo", "pela", "pelos", "pelas", "duma", "dumas", "dum", "duns", "num", "numa", "nuns", "numas", "com", "por", "em");
	
	if (in_array (strtolower($vetitulo[0]), $stopwords))
		$codigo2 = strtolower(substr($vetitulo[1],0,1));
	else
		$codigo2 = strtolower(substr($vetitulo[0],0,1));

// monta o Código Cutter
	$cutter = $codigo1.$cutter.$codigo2;

// monta informações da ficha catalográfica
	
	$texto =  $sobrenome.", ".$nome."\n"
		  .$titulo."  / ".$nome." ".$sobrenome." \n"
		  //."    / ".$titulo." \n"
		 //."    / ".$titulo." \n"
		 .$orientadora." ".$nome_ori." ".$sobrenome_ori;		 
	
	if (!empty($nome_coori))
		$texto .= "; co-orientador$coorientadora ".$nome_coori." ".$sobrenome_coori;
	$texto .=  utf8_decode(". -- São José dos Campos, ").$ano.".\n   $pags p. \n".$titIngles."\n\n\n    "   .$trabalho; 
	
	if ($trabalho == "Tese") 
		$texto .= " (Doutorado"; 
	else 
	$texto .= " (Mestrado";
	$texto .= utf8_decode(" - Programa de Pós-Graduação em $programa) -- Universidade Federal de São Paulo - Instituto de Ciência e Tecnologia, $ano.\n\n\n   1. ").$assunto1.". ";
	
	if (!empty ($assunto2)) 
		$texto .= "2. $assunto2. "; 
	if (!empty ($assunto3)) 
		$texto .= "3. $assunto3. "; 
	if (!empty ($assunto4)) 
		$texto .= "4. $assunto4. "; 
	if (!empty ($assunto5)) 
		$texto .= "5. $assunto5. ";
		
	if (empty($nome_coori))
		$texto .= "I. $sobrenome_ori, $nome_ori".utf8_decode(", orient. II. Título.");
	else
		$texto .= "I. $sobrenome_ori, $nome_ori, orient. II. $sobrenome_coori, $nome_coori,".c.utf8_decode("o-orient. III. Título.");


		  
		
//GERA PDF 
$pdf=new Cezpdf();

$ficha = array (array('cod' => "\n".$cutter, 'ficha' => $texto));

// Gera a ficha em pdf

$pdf -> selectFont('pdf-php/fonts/Helvetica.afm');
$pdf -> ezText ("\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n");
//(ESQ,SUPERIOR,LARGURA,ALTURA)
$pdf -> rectangle(100,65,400,220);
$pdf -> ezText ($titulo_ficha. "\n" . $subtitulo_ficha. "\n\n", 12, array('justification' => 'center'));
$pdf-> selectFont('pdf-php/fonts/Courier.afm');
//ORGANIZA O TEXTO
$pdf -> ezTable ($ficha,'','', array ('fontSize' => 10,'showHeadings'=>0, 'showLines'=>0, 'width'=>345, 'cols' =>array('cod'=>array('width'=>45))));


$pdf->ezStream();    
}
?>