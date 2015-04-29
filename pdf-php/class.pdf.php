<?php
class Cpdf {
//======================================================================
// pdf class
//
// wayne munro - R&OS ltd
// pdf@ros.co.nz
// http://www.ros.co.nz/pdf
//
// this code is a little rough in places, but at present functions well enough
// for the uses that we have put it to. There are a number of possible enhancements
// and if there is sufficient interest then we will extend the functionality.
//
// note that the adobe font AFM files are included, along with some serialized versions
// of their crucial data, though if the serialized versions are not there, then this 
// class will re-create them from the base AFM file for the selected font (if it is there).
// At present only the basic fonts are supported (one of the many restrictions)
//
// this does not yet create a linearised pdf file, this is desirable and will be investigated
// also there is no compression or encryption of the content streams, this will also be added
// as soon as possible.
//
// The site above will have the latest news regarding this code, as well as somewhere to
// register interest in being notified as to future enhancements, and to leave feedback.
//
// IMPORTANT NOTE
// there is no warranty, implied or otherwise with this software.
// 
// LICENCE
// This code has been placed in the Public Domain for all to enjoy.
//
// version 0.06
//======================================================================

var $numObj=0; // the number of pdf objects in the current document
var $objects = array(); // this array contains all of the pdf objects, ready for final assembly
var $catalogId; // object ID of the document catalog
var $fonts=array(); 
var $currentFont='';
var $currentBaseFont='';
var $currentFontNum=0;
var $currentNode;
var $currentPage;
var $currentContents;
var $numFonts=0;
var $currentColour=array('r'=>-1,'g'=>-1,'b'=>-1);
var $currentStrokeColour=array('r'=>-1,'g'=>-1,'b'=>-1);
var $currentLineStyle='';
var $stateStack = array();
var $nStateStack = 0;
var $numPages=0;
var $stack=array(); // the stack will be used to place object Id's onto while others are worked on
var $nStack=0;
var $looseObjects=array();
var $addLooseObjects=array();
var $infoObject=0;
var $numImages=0;
var $options=array('compression'=>1);
var $firstPageId;
var $wordSpaceAdjust=0;
var $procsetObjectId;
var $fontFamilies = array(); // define the relationships between fonts, used to switch to bold italic etc
var $currentTextState = ''; // track if the current font is bolded or italiced
var $messages=''; // store messages during processing, can be selected afterwards to give debug info
//------------------------------------------------------------------------------
// document constructor - starts a new document
function Cpdf ($pageSize=array(0,0,612,792)){
  $this->newDocument($pageSize);
  
  // also initialize the font families that are known about already
  $this->setFontFamily('init');
}

// ==============================================================================
// Document object methods (internal use only)
//
// There is about one object method for each type of object in the pdf document
// Each function has the same call list ($id,$action,$options).
// $id = the object ID of the object, or what it is to be if it is being created
// $action = a string specifying the action to be performed, though ALL must support:
//           'new' - create the object with the id $id
//           'out' - produce the output for the pdf object
// $options = optional, a string or array containing the various parameters for the object
//
// These, in conjunction with the output function are the ONLY way for output to be produced 
// within the pdf 'file'.
// ==============================================================================

// destination object, used to specify the location for the user to jump to, presently on opening
function o_destination($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch($action){
    case 'new':
      $this->objects[$id]=array('t'=>'destination','info'=>array());
      $tmp = '';
      switch ($options['type']){
        case 'XYZ':
        case 'FitR':
          $tmp =  ' '.$options['p3'].$tmp;
        case 'FitH':
        case 'FitV':
        case 'FitBH':
        case 'FitBV':
          $tmp =  ' '.$options['p1'].' '.$options['p2'].$tmp;
        case 'Fit':
        case 'FitB':
          $tmp =  $options['type'].$tmp;
          $this->objects[$id]['info']['string']=$tmp;
          $this->objects[$id]['info']['page']=$options['page'];
      }
      break;
    case 'out':
      $tmp = $o['info'];
      $res="\n".$id." 0 obj\n".'['.$tmp['page'].' 0 R /'.$tmp['string']."]\nendobj\n";
      return $res;
      break;
  }
}

// set the viewer preferences
function o_viewerPreferences($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->objects[$id]=array('t'=>'viewerPreferences','info'=>array());
      break;
    case 'add':
      foreach($options as $k=>$v){
        switch ($k){
          case 'HideToolbar':
          case 'HideMenubar':
          case 'HideWindowUI':
          case 'FitWindow':
          case 'CenterWindow':
          case 'NonFullScreenPageMode':
          case 'Direction':
            $o['info'][$k]=$v;
          break;
        }
      }
      break;
    case 'out':

      $res="\n".$id." 0 obj\n".'<< ';
      foreach($o['info'] as $k=>$v){
        $res.="\n/".$k.' '.$v;
      }
      $res.="\n>>\n";
      return $res;
      break;
  }
}

// define the document catalog, the overall controller for the document
function o_catalog($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->objects[$id]=array('t'=>'catalog','info'=>array());
      $this->catalogId=$id;
      break;
    case 'outlines':
    case 'pages':
    case 'openHere':
      $o['info'][$action]=$options;
      break;
    case 'viewerPreferences':
      if (!isset($o['info']['viewerPreferences'])){
        $this->numObj++;
        $this->o_viewerPreferences($this->numObj,'new');
        $o['info']['viewerPreferences']=$this->numObj;
      }
      $vp = $o['info']['viewerPreferences'];
      $this->o_viewerPreferences($vp,'add',$options);
      break;
    case 'out':
      $res="\n".$id." 0 obj\n".'<< /Type /Catalog';
      foreach($o['info'] as $k=>$v){
        switch($k){
          case 'outlines':
            $res.="\n".'/Outlines '.$v.' 0 R';
            break;
          case 'pages':
            $res.="\n".'/Pages '.$v.' 0 R';
            break;
          case 'viewerPreferences':
            $res.="\n".'/ViewerPreferences '.$o['info']['viewerPreferences'].' 0 R';
            break;
          case 'openHere':
            $res.="\n".'/OpenAction '.$o['info']['openHere'].' 0 R';
            break;
        }
      }
      $res.=" >>\nendobj";
      return $res;
      break;
  }
}

// object which is a parent to the pages in the document
function o_pages($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->objects[$id]=array('t'=>'pages','info'=>array());
      $this->o_catalog($this->catalogId,'pages',$id);
      break;
    case 'page':
      $o['info']['pages'][]=$options;
      break;
    case 'procset':
      $o['info']['procset']=$options;
      break;
    case 'mediaBox':
      $o['info']['mediaBox']=$options; // which should be an array of 4 numbers
      break;
    case 'font':
      $o['info']['fonts'][]=array('objNum'=>$options['objNum'],'fontNum'=>$options['fontNum']);
      break;
    case 'xObject':
      $o['info']['xObjects'][]=array('objNum'=>$options['objNum'],'label'=>$options['label']);
      break;
    case 'out':
      if (count($o['info']['pages'])){
        $res="\n".$id." 0 obj\n<< /Type /Pages\n/Kids [";
        foreach($o['info']['pages'] as $k=>$v){
          $res.=$v." 0 R\n";
        }
        $res.="]\n/Count ".count($this->objects[$id]['info']['pages']);
        if ((isset($o['info']['fonts']) && count($o['info']['fonts'])) || isset($o['info']['procset'])){
          $res.="\n/Resources <<";
          if (isset($o['info']['procset'])){
            $res.="\n/ProcSet ".$o['info']['procset']." 0 R";
          }
          if (isset($o['info']['fonts']) && count($o['info']['fonts'])){
            $res.="\n/Font << ";
            foreach($o['info']['fonts'] as $finfo){
              $res.="\n/F".$finfo['fontNum']." ".$finfo['objNum']." 0 R";
            }
            $res.=" >>";
          }
          if (isset($o['info']['xObjects']) && count($o['info']['xObjects'])){
            $res.="\n/XObject << ";
            foreach($o['info']['xObjects'] as $finfo){
              $res.="\n/".$finfo['label']." ".$finfo['objNum']." 0 R";
            }
            $res.=" >>";
          }
          $res.="\n>>";
          if (isset($o['info']['mediaBox'])){
            $tmp=$o['info']['mediaBox'];
            $res.="\n/MediaBox [".$tmp[0].' '.$tmp[1].' '.$tmp[2].' '.$tmp[3].']';
          }
        }
        $res.="\n >>\nendobj";
      } else {
        $res="\n".$id." 0 obj\n<< /Type /Pages\n/Count 0\n>>\nendobj";
      }
      return $res;
    break;
  }
}

// define the outlines in the doc, empty for now
function o_outlines($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->objects[$id]=array('t'=>'outlines','info'=>array('outlines'=>array()));
      $this->o_catalog($this->catalogId,'outlines',$id);
      break;
    case 'outline':
      $o['info']['outlines'][]=$options;
      break;
    case 'out':
      if (count($o['info']['outlines'])){
        $res="\n".$id." 0 obj\n<< /Type /Outlines /Kids [";
        foreach($o['info']['outlines'] as $k=>$v){
          $res.=$v." 0 R ";
        }
        $res.="] /Count ".count($o['info']['outlines'])." >>\nendobj";
      } else {
        $res="\n".$id." 0 obj\n<< /Type /Outlines /Count 0 >>\nendobj";
      }
      return $res;
      break;
  }
}

// an object to hold the font description
function o_font($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->objects[$id]=array('t'=>'font','info'=>array('name'=>$options['name'],'SubType'=>'Type1'));
      $fontNum=$this->numFonts;
      $this->objects[$id]['info']['fontNum']=$fontNum;
      // deal with the encoding and the differences
      if (isset($options['differences'])){
        // then we'll need an encoding dictionary
        $this->numObj++;
        $this->o_fontEncoding($this->numObj,'new',$options);
        $this->objects[$id]['info']['encodingDictionary']=$this->numObj;
      } else if (isset($options['encoding'])){
        // we can specify encoding here
        switch($options['encoding']){
          case 'WinAnsiEncoding':
          case 'MacRomanEncoding':
          case 'MacExpertEncoding':
            $this->objects[$id]['info']['encoding']=$options['encoding'];
            break;
          case 'none':
            break;
          default:
            $this->objects[$id]['info']['encoding']='WinAnsiEncoding';
            break;
        }
      } else {
        $this->objects[$id]['info']['encoding']='WinAnsiEncoding';
      }
      // also tell the pages node about the new font
      $this->o_pages($this->currentNode,'font',array('fontNum'=>$fontNum,'objNum'=>$id));
      break;
    case 'add':
      foreach ($options as $k=>$v){
        switch ($k){
          case 'BaseFont':
            $o['info']['name'] = $v;
            break;
          case 'FirstChar':
          case 'LastChar':
          case 'Widths':
          case 'FontDescriptor':
          case 'SubType':
            $o['info'][$k] = $v;
            break;
        }
     }
      break;
    case 'out':
      $res="\n".$id." 0 obj\n<< /Type /Font\n/Subtype /".$o['info']['SubType']."\n";
      $res.="/Name /F".$o['info']['fontNum']."\n";
      $res.="/BaseFont /".$o['info']['name']."\n";
      if (isset($o['info']['encodingDictionary'])){
        // then place a reference to the dictionary
        $res.="/Encoding ".$o['info']['encodingDictionary']." 0 R\n";
      } else if (isset($o['info']['encoding'])){
        // use the specified encoding
        $res.="/Encoding /".$o['info']['encoding']."\n";
      }
      if (isset($o['info']['FirstChar'])){
        $res.="/FirstChar ".$o['info']['FirstChar']."\n";
      }
      if (isset($o['info']['LastChar'])){
        $res.="/LastChar ".$o['info']['LastChar']."\n";
      }
      if (isset($o['info']['Widths'])){
        $res.="/Widths ".$o['info']['Widths']." 0 R\n";
      }
      if (isset($o['info']['FontDescriptor'])){
        $res.="/FontDescriptor ".$o['info']['FontDescriptor']." 0 R\n";
      }
      $res.=">>\nendobj";
      return $res;
      break;
  }
}

// a font descriptor, needed for including additional fonts
function o_fontDescriptor($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->objects[$id]=array('t'=>'fontDescriptor','info'=>$options);
      break;
    case 'out':
      $res="\n".$id." 0 obj\n<< /Type /FontDescriptor\n";
      foreach ($o['info'] as $label => $value){
        switch ($label){
          case 'Ascent':
          case 'CapHeight':
          case 'Descent':
          case 'Flags':
          case 'ItalicAngle':
          case 'StemV':
          case 'AvgWidth':
          case 'Leading':
          case 'MaxWidth':
          case 'MissingWidth':
          case 'StemH':
          case 'XHeight':
          case 'CharSet':
            if (strlen($value)){
              $res.='/'.$label.' '.$value."\n";
            }
            break;
          case 'FontFile':
          case 'FontFile2':
          case 'FontFile3':
            $res.='/'.$label.' '.$value." 0 R\n";
            break;
          case 'FontBBox':
            $res.='/'.$label.' ['.$value[0].' '.$value[1].' '.$value[2].' '.$value[3]."]\n";
            break;
          case 'FontName':
            $res.='/'.$label.' /'.$value."\n";
            break;
        }
      }
      $res.=">>\nendobj";
      return $res;
      break;
  }
}

// the font encoding
function o_fontEncoding($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      // the options array should contain 'differences' and maybe 'encoding'
      $this->objects[$id]=array('t'=>'fontEncoding','info'=>$options);
      break;
    case 'out':
      $res="\n".$id." 0 obj\n<< /Type /Encoding\n";
      if (!isset($o['info']['encoding'])){
        $o['info']['encoding']='WinAnsiEncoding';
      }
      $res.="/BaseEncoding /".$o['info']['encoding']."\n";
      $res.="/Differences \n[";
      $onum=-100;
      foreach($o['info']['differences'] as $num=>$label){
        if ($num!=$onum+1){
          // we cannot make use of consecutive numbering
          $res.= "\n".$num." /".$label;
        } else {
          $res.= " /".$label;
        }
        $onum=$num;
      }
      $res.="\n]\n>>\nendobj";
      return $res;
      break;
  }
}

// the document procset, solves some problems with printing to old PS printers
function o_procset($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->objects[$id]=array('t'=>'procset','info'=>array('PDF'=>1,'Text'=>1));
      $this->o_pages($this->currentNode,'procset',$id);
      $this->procsetObjectId=$id;
      break;
    case 'add':
      // this is to add new items to the procset list, despite the fact that this is considered
      // obselete, the items are required for printing to some postscript printers
      switch ($options) {
        case 'ImageB':
        case 'ImageC':
        case 'ImageI':
          $o['info'][$options]=1;
          break;
      }
      break;
    case 'out':
      $res="\n".$id." 0 obj\n[";
      foreach ($o['info'] as $label=>$val){
        $res.='/'.$label.' ';
      }
      $res.="]\nendobj";
      return $res;
      break;
  }
}

// define the document information
function o_info($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->infoObject=$id;
      $date='D:'.date('Ymd');
      $this->objects[$id]=array('t'=>'info','info'=>array('Creator'=>'R and OS php pdf writer, http://www.ros.co.nz','CreationDate'=>$date));
      break;
    case 'Title':
    case 'Author':
    case 'Subject':
    case 'Keywords':
    case 'Creator':
    case 'Producer':
    case 'CreationDate':
    case 'ModDate':
    case 'Trapped':
      $o['info'][$action]=$options;
      break;
    case 'out':
      $res="\n".$id." 0 obj\n<<\n";
      foreach ($o['info']  as $k=>$v){
        $res.='/'.$k.' ('.$this->filterText($v).")\n";
      }
      $res.=">>\nendobj";
      return $res;
      break;
  }
}

// a page object, it also creates a contents object to hold its contents
function o_page($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->numPages++;
      $this->objects[$id]=array('t'=>'page','info'=>array('parent'=>$this->currentNode,'pageNum'=>$this->numPages));
      $this->o_pages($this->currentNode,'page',$id);
      $this->currentPage=$id;
      //make a contents object to go with this page
      $this->numObj++;
      $this->o_contents($this->numObj,'new',$id);
      $this->currentContents=$this->numObj;
      $this->objects[$id]['info']['contents']=array();
      $this->objects[$id]['info']['contents'][]=$this->numObj;
      $match = ($this->numPages%2 ? 'odd' : 'even');
      foreach($this->addLooseObjects as $oId=>$target){
        if ($target=='all' || $match==$target){
          $this->objects[$id]['info']['contents'][]=$oId;
        }
      }
      break;
    case 'content':
      $o['info']['contents'][]=$options;
      break;
    case 'out':
      $res="\n".$id." 0 obj\n<< /Type /Page";
      $res.="\n/Parent ".$o['info']['parent']." 0 R";
      $count = count($o['info']['contents']);
      if ($count==1){
        $res.="\n/Contents ".$o['info']['contents'][0]." 0 R";
      } else if ($count>1){
        $res.="\n/Contents [\n";
        foreach ($o['info']['contents'] as $cId){
          $res.=$cId." 0 R\n";
        }
        $res.="]";
      }
      $res.="\n>>\nendobj";
      return $res;
      break;
  }
}

// the contents objects hold all of the content which appears on pages
function o_contents($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch ($action){
    case 'new':
      $this->objects[$id]=array('t'=>'contents','c'=>'','info'=>array());
      if (strlen($options) && intval($options)){
        // then this contents is the primary for a page
        $this->objects[$id]['onPage']=$options;
      } else if ($options=='raw'){
        // then this page contains some other type of system object
        $this->objects[$id]['raw']=1;
      }
      break;
    case 'add':
      // add more options to the decleration
      foreach ($options as $k=>$v){
        $o['info'][$k]=$v;
      }
    case 'out':
      $tmp=$o['c'];
      $res= "\n".$id." 0 obj\n";
      if (isset($this->objects[$id]['raw'])){
        $res.=$tmp;
      } else {
        $res.= "<<";
        if (function_exists('gzcompress') && $this->options['compression']){
          // then implement ZLIB based compression on this content stream
          $res.=" /Filter /FlateDecode";
          $tmp = gzcompress($tmp);
        }
        foreach($o['info'] as $k=>$v){
          $res .= "\n/".$k.' '.$v;
        }
        $res.="\n/Length ".strlen($tmp)." >>\nstream\n".$tmp."\nendstream";
      }
      $res.="\nendobj\n";
      return $res;
      break;
  }
}

// an image object, will be an XObject in the document, includes description and data
function o_image($id,$action,$options=''){
  if ($action!='new'){
    $o =& $this->objects[$id];
  }
  switch($action){
    case 'new':
      // make the new object
      $this->objects[$id]=array('t'=>'image','data'=>$options['data'],'info'=>array());
      $this->objects[$id]['info']['Type']='/XObject';
      $this->objects[$id]['info']['Subtype']='/Image';
      $this->objects[$id]['info']['Width']=$options['iw'];
      $this->objects[$id]['info']['Height']=$options['ih'];
      $this->objects[$id]['info']['ColorSpace']='/DeviceRGB';
      $this->objects[$id]['info']['BitsPerComponent']=8;
      $this->objects[$id]['info']['Filter']='/DCTDecode';
      // assign it a place in the named resource dictionary as an external object, according to
      // the label passed in with it.
      $this->o_pages($this->currentNode,'xObject',array('label'=>$options['label'],'objNum'=>$id));
      // also make sure that we have the right procset object for it.
      $this->o_procset($this->procsetObjectId,'add','ImageC');
      break;
    case 'out':
      $tmp=$o['data'];
      $res= "\n".$id." 0 obj\n<<";
      foreach($o['info'] as $k=>$v){
        $res.="\n/".$k.' '.$v;
      }
      $res.="\n/Length ".strlen($tmp)." >>\nstream\n".$tmp."\nendstream\nendobj\n";
      return $res;
      break;
  }
}

// ==============================================================================

function checkAllHere(){
  // make sure that anything that needs to be in the file has been included
}

function output($debug=0){

  if ($debug){
    // turn compression off
    $this->options['compression']=0;
  }

  $this->checkAllHere();

  $xref=array();
  $content="%PDF-1.3\n%âãÏÓ\n";
//  $content="%PDF-1.3\n";
  $pos=strlen($content);
  foreach($this->objects as $k=>$v){
    $tmp='o_'.$v['t'];
    $cont=$this->$tmp($k,'out');
    $content.=$cont;
    $xref[]=$pos;
    $pos+=strlen($cont);
  }
  $content.="\nxref\n0 ".(count($xref)+1)."\n0000000000 65535 f \n";
  foreach($xref as $p){
    $content.=substr('0000000000',0,10-strlen($p)).$p." 00000 n \n";
  }
  $content.=
'
trailer
  << /Size '.(count($xref)+1).'
     /Root 1 0 R
     /Info '.$this->infoObject.' 0 R
  >>
startxref
'.$pos.'
%%EOF
';
  return $content;
}

// ==============================================================================

function newDocument($pageSize=array(0,0,612,792)){
  $this->numObj=0;
  $this->objects = array();

  $this->numObj++;
  $this->o_catalog($this->numObj,'new');

  $this->numObj++;
  $this->o_outlines($this->numObj,'new');

  $this->numObj++;
  $this->o_pages($this->numObj,'new');

  $this->o_pages($this->numObj,'mediaBox',$pageSize);
  $this->currentNode = 3;

  $this->numObj++;
  $this->o_procset($this->numObj,'new');

  $this->numObj++;
  $this->o_info($this->numObj,'new');

  $this->numObj++;
  $this->o_page($this->numObj,'new');

  // need to store the first page id as there is no way to get it to the user during 
  // startup
  $this->firstPageId = $this->currentContents;
}

// ------------------------------------------------------------------------------

function openFont($font){
  // open the font file and return a php structure containing it.
  // first check if this one has been done before and saved in a form more suited to php
  // note that if a php serialized version does not exist it will try and make one, but will
  // require write access to the directory to do it... it is MUCH faster to have these serialized
  // files.
  
  // assume that $font contains both the path and perhaps the extension to the file, split them
  $pos=strrpos($font,'/');
  if ($pos===false){
    $dir = './';
    $name = $font;
  } else {
    $dir=substr($font,0,$pos+1);
    $name=substr($font,$pos+1);
  }

  if (substr($name,-4)=='.afm'){
    $name=substr($name,0,strlen($name)-4);
  }
  $this->addMessage('openFont: '.$font.' - '.$name);
  if (file_exists($dir.'php_'.$name.'.afm')){
    $this->addMessage('openFont: php file exists '.$dir.'php_'.$name.'.afm');
    $tmp = file($dir.'php_'.$name.'.afm');
    $this->fonts[$font]=unserialize($tmp[0]);
    if (!isset($this->fonts[$font]['_version_']) || $this->fonts[$font]['_version_']<1){
      // if the font file is old, then clear it out and prepare for re-creation
      $this->addMessage('openFont: clear out, make way for new version.');
      unset($this->fonts[$font]);
    }
  }
  if (!isset($this->fonts[$font]) && file_exists($dir.$name.'.afm')){
    // then rebuild the php_<font>.afm file from the <font>.afm file
    $this->addMessage('openFont: build php file from '.$dir.$name.'.afm');
    $data = array();
    $file = file($dir.$name.'.afm');
    foreach ($file as $rowA){
      $row=trim($rowA);
      $pos=strpos($row,' ');
      if ($pos){
        // then there must be some keyword
        $key = substr($row,0,$pos);
        switch ($key){
          case 'FontName':
          case 'FullName':
          case 'FamilyName':
          case 'Weight':
          case 'ItalicAngle':
          case 'IsFixedPitch':
          case 'CharacterSet':
          case 'UnderlinePosition':
          case 'UnderlineThickness':
          case 'Version':
          case 'EncodingScheme':
          case 'CapHeight':
          case 'XHeight':
          case 'Ascender':
          case 'Descender':
          case 'StdHW':
          case 'StdVW':
          case 'StartCharMetrics':
            $data[$key]=trim(substr($row,$pos));
            break;
          case 'FontBBox':
            $data[$key]=explode(' ',trim(substr($row,$pos)));
            break;
          case 'C':
            //C 39 ; WX 222 ; N quoteright ; B 53 463 157 718 ;
            $bits=explode(';',trim($row));
            $dtmp=array();
            foreach($bits as $bit){
              $bits2 = explode(' ',trim($bit));
              if (strlen($bits2[0])){
                if (count($bits2)>2){
                  $dtmp[$bits2[0]]=array();
                  for ($i=1;$i<count($bits2);$i++){
                    $dtmp[$bits2[0]][]=$bits2[$i];
                  }
                } else if (count($bits2)==2){
                  $dtmp[$bits2[0]]=$bits2[1];
                }
              }
            }
            if ($dtmp['C']>=0){
              $data['C'][$dtmp['C']]=$dtmp;
              $data['C'][$dtmp['N']]=$dtmp;
            } else {
              $data['C'][$dtmp['N']]=$dtmp;
            }
            break;
          case 'KPX':
            //KPX Adieresis yacute -40
            $bits=explode(' ',trim($row));
            $data['KPX'][$bits[1]][$bits[2]]=$bits[3];
            break;
        }
      }
    }
    $data['_version_']=1;
    $this->fonts[$font]=$data;
    $fp = fopen($dir.'php_'.$name.'.afm','w');
    fwrite($fp,serialize($data));
    fclose($fp);
  } else if (!isset($this->fonts[$font])){
    $this->addMessage('openFont: no font file found');
//    echo 'Font not Found '.$font;
  }
}

// ------------------------------------------------------------------------------

function selectFont($fontName,$encoding='',$set=1){
  // if the font is not loaded then load it and make the required object
  // else just make it the current font
  // the encoding array can contain 'encoding'=> 'none','WinAnsiEncoding','MacRomanEncoding' or 'MacExpertEncoding'
  // note that encoding='none' will need to be used for symbolic fonts
  //    and 'differences' => an array of mappings between numbers 0->255 and character names.
  if (!isset($this->fonts[$fontName])){
    // load the file
    $this->openFont($fontName);
    if (isset($this->fonts[$fontName])){
      $this->numObj++;
      $this->numFonts++;
      $pos=strrpos($fontName,'/');
//      $dir=substr($fontName,0,$pos+1);
      $name=substr($fontName,$pos+1);
      if (substr($name,-4)=='.afm'){
        $name=substr($name,0,strlen($name)-4);
      }
      $options=array('name'=>$name);
      if (is_array($encoding)){
        // then encoding and differences might be set
        if (isset($encoding['encoding'])){
          $options['encoding']=$encoding['encoding'];
        }
        if (isset($encoding['differences'])){
          $options['differences']=$encoding['differences'];
        }
      } else if (strlen($encoding)){
        // then perhaps only the encoding has been set
        $options['encoding']=$encoding;
      }
      $this->o_font($this->numObj,'new',$options);
      $this->fonts[$fontName]['fontNum']=$this->numFonts;
      // if this is a '.afm' font, and there is a '.pfa' file to go with it ( as there
      // should be for all non-basic fonts), then load it into an object and put the
      // references into the font object
      $basefile = substr($fontName,0,strlen($fontName)-4);
      if (file_exists($basefile.'.pfb')){
        $fbtype = 'pfb';
      } else if (file_exists($basefile.'.ttf')){
        $fbtype = 'ttf';
      } else {
        $fbtype='';
      }
      $fbfile = $basefile.'.'.$fbtype;
      
//      $pfbfile = substr($fontName,0,strlen($fontName)-4).'.pfb';
//      $ttffile = substr($fontName,0,strlen($fontName)-4).'.ttf';
      $this->addMessage('selectFont: checking for - '.$fbfile);
      if (substr($fontName,-4)=='.afm' && strlen($fbtype) ){
        $adobeFontName = $this->fonts[$fontName]['FontName'];
        $fontObj = $this->numObj;
        $this->addMessage('selectFont: adding font file - '.$fbfile.' - '.$adobeFontName);
        // find the array of fond widths, and put that into an object.
        $firstChar = -1;
        $lastChar = 0;
        $widths = array();
        foreach ($this->fonts[$fontName]['C'] as $num=>$d){
//          echo $num."<br>";
          if (intval($num)>0 || $num=='0'){
            if ($lastChar>0 && $num>$lastChar+1){
              for($i=$lastChar+1;$i<$num;$i++){
                $widths[] = 0;
              }
            }
            $widths[] = $d['WX'];
            if ($firstChar==-1){
              $firstChar = $num;
            }
            $lastChar = $num;
          }
        }
        $this->addMessage('selectFont: FirstChar='.$firstChar);
        $this->addMessage('selectFont: LastChar='.$lastChar);
        $this->numObj++;
        $this->o_contents($this->numObj,'new','raw');
        $this->objects[$this->numObj]['c'].='[';
        foreach($widths as $width){
          $this->objects[$this->numObj]['c'].=' '.$width;
        }
        $this->objects[$this->numObj]['c'].=' ]';
        $widthid = $this->numObj;

        // load the pfb file, and put that into an object too.
        // note that pdf supports only binary format type 1 font files, though there is a 
        // simple utility to convert them from pfa to pfb.
        $fp = fopen($fbfile,'rb');
        $tmp = get_magic_quotes_runtime();
        set_magic_quotes_runtime(0);
        $data = fread($fp,filesize($fbfile));
        set_magic_quotes_runtime($tmp);
        fclose($fp);

//        $data = substr($data,4);
        
        // create the font descriptor
        $this->numObj++;
        $fontDescriptorId = $this->numObj;
        $this->numObj++;
        $pfbid = $this->numObj;
        // determine flags (more than a little flakey, hopefully will not matter much)
        $flags=0;
        if ($this->fonts[$fontName]['ItalicAngle']!=0){ $flags+=pow(2,6); }
        if ($this->fonts[$fontName]['IsFixedPitch']=='true'){ $flags+=1; }
        $flags+=pow(2,5); // assume non-sybolic

        $fdopt = array(
          'Ascent'=>$this->fonts[$fontName]['Ascender']
         ,'CapHeight'=>$this->fonts[$fontName]['CapHeight']
         ,'Descent'=>$this->fonts[$fontName]['Descender']
         ,'Flags'=>$flags
         ,'FontBBox'=>$this->fonts[$fontName]['FontBBox']
         ,'FontName'=>$adobeFontName
         ,'ItalicAngle'=>$this->fonts[$fontName]['ItalicAngle']
         ,'StemV'=>100  // don't know what the value for this should be!
//         ,'FontFile'=>$pfbid
        );
        if ($fbtype=='pfb'){
          $fdopt['FontFile']=$pfbid;
        } else if ($fbtype=='ttf'){
          $fdopt['FontFile2']=$pfbid;
        }
        $this->o_fontDescriptor($fontDescriptorId,'new',$fdopt);        

        // embed the font program
        $this->o_contents($this->numObj,'new');
        $this->objects[$pfbid]['c'].=$data;
        // determine the cruicial lengths within this file
        if ($fbtype=='pfb'){
          $l1 = strpos($data,'eexec')+6;
          $l2 = strpos($data,'000000000000000000000000000000')-$l1;
          $l3 = strlen($data)-$l2-$l1;
          $this->o_contents($this->numObj,'add',array('Length1'=>$l1,'Length2'=>$l2,'Length3'=>$l3));
        } else if ($fbtype=='ttf'){
          $l1 = strlen($data);
          $this->o_contents($this->numObj,'add',array('Length1'=>$l1));
        }


        // tell the font object about all this new stuff
        $tmp = array('BaseFont'=>$adobeFontName,'Widths'=>$widthid
                                      ,'FirstChar'=>$firstChar,'LastChar'=>$lastChar
                                      ,'FontDescriptor'=>$fontDescriptorId);
        if ($fbtype=='ttf'){
          $tmp['SubType']='TrueType';
        }
        $this->o_font($fontObj,'add',$tmp);

      } else {
        $this->addMessage('selectFont: pfb or ttf file not found, ok if this is one of the 14 standard fonts');
      }


      // also set the differences here, note that this means that these will take effect only the 
      //first time that a font is selected, else they are ignored
      if (isset($options['differences'])){
        $this->fonts[$fontName]['differences']=$options['differences'];
      }
    }
  }
  if ($set && isset($this->fonts[$fontName])){
    // so if for some reason the font was not set in the last one then it will not be selected
    $this->currentBaseFont=$fontName;
    // the next line means that if a new font is selected, then the current text state will be
    // applied to it as well.
    $this->setCurrentFont();
  }
  return $this->currentFontNum;
}

// ------------------------------------------------------------------------------

// returns the current font number, taking into account the settings of the 
// currentTextState
// note that this system is quite flexible, a <b><i> font can be completely different to a
// <i><b> font, and even <b><b> will have to be defined within the family to have meaning
// This function is to be called whenever the currentTextState is changed, it will update
// the currentFont setting to whatever the appropriatte family one is.
// If the user calls selectFont themselves then that will reset the currentBaseFont, and the currentFont
// This function will change the currentFont to whatever it should be, but will not change the 
// currentBaseFont.
function setCurrentFont(){
  if (strlen($this->currentBaseFont)==0){
    // then assume an initial font
    $this->selectFont('./fonts/Helvetica.afm');
  }
  $cf = substr($this->currentBaseFont,strrpos($this->currentBaseFont,'/')+1);
  if (strlen($this->currentTextState)
    && isset($this->fontFamilies[$cf]) 
      && isset($this->fontFamilies[$cf][$this->currentTextState])){
    // then we are in some state or another
    // and this font has a family, and the current setting exists within it
    // select the font, then return it
    $nf = substr($this->currentBaseFont,0,strrpos($this->currentBaseFont,'/')+1).$this->fontFamilies[$cf][$this->currentTextState];
    $this->selectFont($nf,'',0);
    $this->currentFont = $nf;
    $this->currentFontNum = $this->fonts[$nf]['fontNum'];
  } else {
    // the this font must not have the right family member for the current state
    // simply assume the base font
    $this->currentFont = $this->currentBaseFont;
    $this->currentFontNum = $this->fonts[$this->currentFont]['fontNum'];    
  }
}

// ------------------------------------------------------------------------------

// function for the user to find out what the ID is of the first page that was created during
// startup - useful if they wish to add something to it later.
function getFirstPageId(){
  return $this->firstPageId;
}

// ------------------------------------------------------------------------------

function addContent($content){
  $this->objects[$this->currentContents]['c'].=$content;
}

// ------------------------------------------------------------------------------

function setColor($r,$g,$b,$force=0){
  if ($r>=0 && ($force || $r!=$this->currentColour['r'] || $g!=$this->currentColour['g'] || $b!=$this->currentColour['b'])){
    $this->objects[$this->currentContents]['c'].="\n".sprintf('%.3f',$r).' '.sprintf('%.3f',$g).' '.sprintf('%.3f',$b).' rg';
    $this->currentColour=array('r'=>$r,'g'=>$g,'b'=>$b);
  }
}

// ------------------------------------------------------------------------------

function setStrokeColor($r,$g,$b,$force=0){
  if ($r>=0 && ($force || $r!=$this->currentStrokeColour['r'] || $g!=$this->currentStrokeColour['g'] || $b!=$this->currentStrokeColour['b'])){
    $this->objects[$this->currentContents]['c'].="\n".sprintf('%.3f',$r).' '.sprintf('%.3f',$g).' '.sprintf('%.3f',$b).' RG';
    $this->currentStrokeColour=array('r'=>$r,'g'=>$g,'b'=>$b);
  }
}

// ------------------------------------------------------------------------------

function line($x1,$y1,$x2,$y2){
  $this->objects[$this->currentContents]['c'].="\n".sprintf('%.3f',$x1).' '.sprintf('%.3f',$y1).' m '.sprintf('%.3f',$x2).' '.sprintf('%.3f',$y2).' l S';
}

// ------------------------------------------------------------------------------

function curve($x0,$y0,$x1,$y1,$x2,$y2,$x3,$y3){
  // in the current line style, draw a bezier curve from (x0,y0) to (x3,y3) using the other two points
  // as the control points for the curve.
  $this->objects[$this->currentContents]['c'].="\n".sprintf('%.3f',$x0).' '.sprintf('%.3f',$y0).' m '.sprintf('%.3f',$x1).' '.sprintf('%.3f',$y1);
  $this->objects[$this->currentContents]['c'].= ' '.sprintf('%.3f',$x2).' '.sprintf('%.3f',$y2).' '.sprintf('%.3f',$x3).' '.sprintf('%.3f',$y3).' c S';
}

// ------------------------------------------------------------------------------

function ellipse($x0,$y0,$r1,$r2=0,$angle=0,$nSeg=8){
  // draws an ellipse in the current line style
  // centered at $x0,$y0, radii $r1,$r2
  // if $r2 is not set, then a circle is drawn
  // nSeg is not allowed to be less than 2, as this will simply draw a line (and will even draw a 
  // pretty crappy shape at 2, as we are approximating with bezier curves.
  if ($r1==0){
    return;
  }
  if ($r2==0){
    $r2=$r1;
  }
  if ($nSeg<2){
    $nSeg=2;
  }
  $dt = 2*pi()/$nSeg;
  $dtm = $dt/3;

  if ($angle != 0){
    $a = -1*deg2rad((float)$angle);
    $tmp = "\n q ";
    $tmp .= sprintf('%.3f',cos($a)).' '.sprintf('%.3f',(-1.0*sin($a))).' '.sprintf('%.3f',sin($a)).' '.sprintf('%.3f',cos($a)).' ';
    $tmp .= sprintf('%.3f',$x0).' '.sprintf('%.3f',$y0).' cm';
    $this->objects[$this->currentContents]['c'].= $tmp;
    $x0=0;
    $y0=0;
  }

  $a0=$x0+$r1;
  $b0=$y0;
  $c0=0;
  $d0=$r2;

  $this->objects[$this->currentContents]['c'].="\n".sprintf('%.3f',$a0).' '.sprintf('%.3f',$b0).' m ';
  for ($i=1;$i<=$nSeg;$i++){
    // draw this bit of the total curve
    $t1 = $i*$dt;
    $a1 = $x0+$r1*cos($t1);
    $b1 = $y0+$r2*sin($t1);
    $c1 = -$r1*sin($t1);
    $d1 = $r2*cos($t1);
    $this->objects[$this->currentContents]['c'].="\n".sprintf('%.3f',($a0+$c0*$dtm)).' '.sprintf('%.3f',($b0+$d0*$dtm));
    $this->objects[$this->currentContents]['c'].= ' '.sprintf('%.3f',($a1-$c1*$dtm)).' '.sprintf('%.3f',($b1-$d1*$dtm)).' '.sprintf('%.3f',$a1).' '.sprintf('%.3f',$b1).' c';
    $a0=$a1;
    $b0=$b1;
    $c0=$c1;
    $d0=$d1;    
  }
  $this->objects[$this->currentContents]['c'].=' s'; // small 's' signifies closing the path as well
  if ($angle !=0){
    $this->objects[$this->currentContents]['c'].=' Q';
  }
}

// ------------------------------------------------------------------------------

function setLineStyle($width=1,$cap='',$join='',$dash='',$phase=0){
  // this sets the line drawing style.
  // width, is the thickness of the line in user units
  // cap is the type of cap to put on the line, values can be 'butt','round','square'
  //    where the diffference between 'square' and 'butt' is that 'square' projects a flat end past the
  //    end of the line.
  // join can be 'miter', 'round', 'bevel'
  // dash is an array which sets the dash pattern, is a series of length values, which are the lengths of the
  //   on and off dashes.
  //   (2) represents 2 on, 2 off, 2 on , 2 off ...
  //   (2,1) is 2 on, 1 off, 2 on, 1 off.. etc
  // phase is a modifier on the dash pattern which is used to shift the point at which the pattern starts. 

  // this is quite inefficient in that it sets all the parameters whenever 1 is changed, but will fix another day

  $string = '';
  if ($width>0){
    $string.= $width.' w';
  }
  $ca = array('butt'=>0,'round'=>1,'square'=>2);
  if (isset($ca[$cap])){
    $string.= ' '.$ca[$cap].' J';
  }
  $ja = array('miter'=>0,'round'=>1,'bevel'=>2);
  if (isset($ja[$join])){
    $string.= ' '.$ja[$join].' j';
  }
  if (is_array($dash)){
    $string.= ' [';
    foreach ($dash as $len){
      $string.=' '.$len;
    }
    $string.= ' ] '.$phase.' d';
  }
  $this->currentLineStyle = $string;
  $this->objects[$this->currentContents]['c'].="\n".$string;
}

// ------------------------------------------------------------------------------

function polygon($p,$np,$f=0){
  $this->objects[$this->currentContents]['c'].="\n";
  $this->objects[$this->currentContents]['c'].=sprintf('%.3f',$p[0]).' '.sprintf('%.3f',$p[1]).' m ';
  for ($i=2;$i<$np*2;$i=$i+2){
    $this->objects[$this->currentContents]['c'].= sprintf('%.3f',$p[$i]).' '.sprintf('%.3f',$p[$i+1]).' l ';
  }
  if ($f==1){
    $this->objects[$this->currentContents]['c'].=' f';
  } else {
    $this->objects[$this->currentContents]['c'].=' S';
  }
}

// ------------------------------------------------------------------------------

function filledRectangle($x1,$y1,$width,$height){
  $this->objects[$this->currentContents]['c'].="\n".sprintf('%.3f',$x1).' '.sprintf('%.3f',$y1).' '.$width.' '.$height.' re f';
}

// ------------------------------------------------------------------------------

function rectangle($x1,$y1,$width,$height){
  $this->objects[$this->currentContents]['c'].="\n".sprintf('%.3f',$x1).' '.sprintf('%.3f',$y1).' '.$width.' '.$height.' re S';
}

// ------------------------------------------------------------------------------

function newPage(){

  // if there is a state saved, then go up the stack closing them
  // then on the new page, re-open them with the right setings
  
  if ($this->nStateStack){
    for ($i=$this->nStateStack;$i>=1;$i--){
      $this->restoreState($i);
    }
  }

  $this->numObj++;
  $this->o_page($this->numObj,'new');
  // if there is a stack saved, then put that onto the page
  if ($this->nStateStack){
    for ($i=1;$i<=$this->nStateStack;$i++){
      $this->saveState($i);
    }
  }  
  // and if there has been a stroke or fill colour set, then transfer them
  if ($this->currentColour['r']>=0){
    $this->setColor($this->currentColour['r'],$this->currentColour['g'],$this->currentColour['b'],1);
  }
  if ($this->currentStrokeColour['r']>=0){
    $this->setStrokeColor($this->currentStrokeColour['r'],$this->currentStrokeColour['g'],$this->currentStrokeColour['b'],1);
  }

  // if there is a line style set, then put this in too
  if (strlen($this->currentLineStyle)){
    $this->objects[$this->currentContents]['c'].="\n".$this->currentLineStyle;
  }

  // the call to the o_page object set currentContents to the present page, so this can be returned as the page id
  return $this->currentContents;
}

// ------------------------------------------------------------------------------

function stream($options=''){
  // setting the options allows the adjustment of the headers
  // values at the moment are:
  // 'Content-Disposition'=>'filename'  - sets the filename, though not too sure how well this will 
  //        work as in my trial the browser seems to use the filename of the php file with .pdf on the end
  // 'Accept-Ranges'=>1 or 0 - if this is not set to 1, then this header is not included, off by default
  //    this header seems to have caused some problems despite tha fact that it is supposed to solve
  //    them, so I am leaving it off by default.
  // 'compress'=> 1 or 0 - apply content stream compression, this is on (1) by default
  if (!is_array($options)){
    $options=array();
  }
  if ( isset($options['compress']) && $options['compress']==0){
    $tmp = $this->output(1);
  } else {
    $tmp = $this->output();
  }
  header("Content-type: application/pdf");
  header("Content-Length: ".strlen(trim($tmp)));
  $fileName = (isset($options['Content-Disposition'])?$options['Content-Disposition']:'file.pdf');
  header("Content-Disposition: inline; filename=".$fileName);
  if (isset($options['Accept-Ranges']) && $options['Accept-Ranges']==1){
    header("Accept-Ranges: ".strlen(trim($tmp))); 
  }
  echo trim($tmp);
}

// ------------------------------------------------------------------------------

function getFontHeight($size){
  if (!$this->numFonts){
    $this->selectFont('./fonts/Helvetica');
  }
  // for the current font, and the given size, what is the height of the font in user units
  $h = $this->fonts[$this->currentFont]['FontBBox'][3]-$this->fonts[$this->currentFont]['FontBBox'][1];
  return $size*$h/1000;
}

// ------------------------------------------------------------------------------

function getFontDecender($size){
  // note that this will most likely return a negative value
  if (!$this->numFonts){
    $this->selectFont('./fonts/Helvetica');
  }
  $h = $this->fonts[$this->currentFont]['FontBBox'][1];
  return $size*$h/1000;
}

// ------------------------------------------------------------------------------

function filterText($text){
  $text = str_replace('\\','\\\\',$text);
  $text = str_replace('(','\(',$text);
  $text = str_replace(')','\)',$text);
  $text = str_replace('&lt;','<',$text);
  $text = str_replace('&gt;','>',$text);
  $text = str_replace('&#039;','\'',$text);
  $text = str_replace('&quot;','"',$text);
  $text = str_replace('&amp;','&',$text);

  return $text;
}

// ------------------------------------------------------------------------------

function PRVTcheckTextDirective(&$text,$i){
  // checks if the text stream contains a control directive
  // if so then makes some changes and returns the number of characters involved in the directive
  $directive = 0;
  if ($text[$i]=='<'){
    // then this may be a directive
    if (substr($text,$i,3)=='<b>'){
      // change to bold
      $this->currentTextState.='b';
      $directive=3;
    } else if (substr($text,$i,4)=='</b>'){
      // change from bold
      $p = strrpos($this->currentTextState,'b');
      if ($p !== false){
        // then there is one to remove
        $this->currentTextState = substr($this->currentTextState,0,$p).substr($this->currentTextState,$p+1);
      }
      $directive=4;
    } else if (substr($text,$i,3)=='<i>'){
      // change to italic
      $this->currentTextState.='i';
      $directive=3;
    } else if (substr($text,$i,4)=='</i>'){
      // change from italic
      $p = strrpos($this->currentTextState,'i');
      if ($p !== false){
        // then there is one to remove
        $this->currentTextState = substr($this->currentTextState,0,$p).substr($this->currentTextState,$p+1);
      }
      $directive=4;
    }
  }
  return $directive;
}

// ------------------------------------------------------------------------------

function addText($x,$y,$size,$text,$angle=0,$wordSpaceAdjust=0){
  if (!$this->numFonts){$this->selectFont('./fonts/Helvetica');}
  if ($angle==0){
//    $this->objects[$this->currentContents]['c'].="\n".'BT /F'.$this->currentFontNum.' '.sprintf('%.1f',$size).' Tf '.sprintf('%.3f',$x).' '.sprintf('%.3f',$y).' Td';
    $this->objects[$this->currentContents]['c'].="\n".'BT '.sprintf('%.3f',$x).' '.sprintf('%.3f',$y).' Td';
    if ($wordSpaceAdjust!=0 || $wordSpaceAdjust != $this->wordSpaceAdjust){
      $this->wordSpaceAdjust=$wordSpaceAdjust;
      $this->objects[$this->currentContents]['c'].=' '.sprintf('%.3f',$wordSpaceAdjust).' Tw';
    }
    $len=strlen($text);
    $start=0;
    for ($i=0;$i<$len;$i++){
      $directive = $this->PRVTcheckTextDirective($text,$i);
      if ($directive){
        // then we should write what we need to
        if ($i>$start){
          $part = substr($text,$start,$i-$start);
          $this->objects[$this->currentContents]['c'].=' /F'.$this->currentFontNum.' '.sprintf('%.1f',$size).' Tf ';
          $this->objects[$this->currentContents]['c'].=' ('.$this->filterText($part).') Tj';
        }
        // set the new font
        $this->setCurrentFont();
        // and move the writing point to the next piece of text
        $i=$i+$directive-1;
        $start=$i+1;
      }
      
    }
    if ($start<$len){
      $part = substr($text,$start);
      $this->objects[$this->currentContents]['c'].=' /F'.$this->currentFontNum.' '.sprintf('%.1f',$size).' Tf ';
      $this->objects[$this->currentContents]['c'].=' ('.$this->filterText($part).') Tj';
    }
    $this->objects[$this->currentContents]['c'].=' ET';
  } else {
    // then we are going to need a modification matrix
    // assume the angle is in degrees
    $text=$this->filterText($text);
    $a = deg2rad((float)$angle);
    $tmp = "\n".'BT /F'.$this->currentFontNum.' '.sprintf('%.1f',$size).' Tf ';
    $tmp .= sprintf('%.3f',cos($a)).' '.sprintf('%.3f',(-1.0*sin($a))).' '.sprintf('%.3f',sin($a)).' '.sprintf('%.3f',cos($a)).' ';
    $tmp .= sprintf('%.3f',$x).' '.sprintf('%.3f',$y).' Tm';
    $tmp.= ' ('.$text.') Tj ET';
    $this->objects[$this->currentContents]['c'].=$tmp;
  }
}

// ------------------------------------------------------------------------------

function getTextWidth($size,$text){
  // this function should not change any of the settings, though it will need to
  // track any directives which change during calculation, so copy them at the start
  // and put them back at the end.
  $store_currentTextState = $this->currentTextState;

  if (!$this->numFonts){
    $this->selectFont('./fonts/Helvetica');
  }
  // hmm, this is where it all starts to get tricky - use the font information to
  // calculate the width of each character, add them up and convert to user units
  $w=0;
  $len=strlen($text);
  $cf = $this->currentFont;
  for ($i=0;$i<$len;$i++){
    $directive = $this->PRVTcheckTextDirective($text,$i);
    if ($directive){
      $this->setCurrentFont();
      $cf = $this->currentFont;
      $i=$i+$directive-1;
    } else {
      $char=ord($text[$i]);
      if (isset($this->fonts[$cf]['differences'][$char])){
        // then this character is being replaced by another
        $name = $this->fonts[$cf]['differences'][$char];
        if (isset($this->fonts[$cf]['C'][$name]['WX'])){
          $w+=$this->fonts[$cf]['C'][$name]['WX'];
        }
      } else if (isset($this->fonts[$cf]['C'][$char]['WX'])){
        $w+=$this->fonts[$cf]['C'][$char]['WX'];
      }
    }
  }
  
  $this->currentTextState = $store_currentTextState;
  $this->setCurrentFont();

  return $w*$size/1000;
}

// ------------------------------------------------------------------------------

function PRVTadjustWrapText($text,$actual,$width,&$x,&$adjust,$justification){
  switch ($justification){
    case 'left':
      return;
      break;
    case 'right':
      $x+=$width-$actual;
      break;
    case 'center':
    case 'centre':
      $x+=($width-$actual)/2;
      break;
    case 'full':
      // count the number of words
      $words = explode(' ',$text);
      $nspaces=count($words)-1;
      if ($nspaces>0){
        $adjust = ($width-$actual)/$nspaces;
      } else {
        $adjust=0;
      }
      break;
  }
}

// ------------------------------------------------------------------------------

function addTextWrap($x,$y,$width,$size,$text,$justification='left',$angle=0){
  // this will display the text, and if it goes beyond the width $width, will backtrack to the 
  // previous space or hyphen, and return the remainder of the text.

  // $justification can be set to 'left','right','center','centre','full'

  // need to store the initial text state, as this will change during the width calculation
  // but will need to be re-set before printing, so that the chars work out right
  $store_currentTextState = $this->currentTextState;

  if (!$this->numFonts){$this->selectFont('./fonts/Helvetica');}
  if ($width<=0){
    // error, pretend it printed ok, otherwise risking a loop
    return '';
  }
  $w=0;
  $break=0;
  $breakWidth=0;
  $len=strlen($text);
  $cf = $this->currentFont;
  $tw = $width/$size*1000;
  for ($i=0;$i<$len;$i++){
    $directive = $this->PRVTcheckTextDirective($text,$i);
    if ($directive){
      $this->setCurrentFont();
      $cf = $this->currentFont;
      $i=$i+$directive-1;
    } else {
      $cOrd = ord($text[$i]);
      if (isset($this->fonts[$cf]['differences'][$cOrd])){
        // then this character is being replaced by another
        $cOrd2 = $this->fonts[$cf]['differences'][$cOrd];
      } else {
        $cOrd2 = $cOrd;
      }
  
      if (isset($this->fonts[$cf]['C'][$cOrd2]['WX'])){
        $w+=$this->fonts[$cf]['C'][$cOrd2]['WX'];
      }
      if ($w>$tw){
        // then we need to truncate this line
        if ($break>0){
          // then we have somewhere that we can split :)
          if ($text[$break]==' '){
            $tmp = substr($text,0,$break);
          } else {
            $tmp = substr($text,0,$break+1);
          }
          $adjust=0;
          $this->PRVTadjustWrapText($tmp,$breakWidth,$width,$x,$adjust,$justification);

          // reset the text state
          $this->currentTextState = $store_currentTextState;
          $this->setCurrentFont();
          $this->addText($x,$y,$size,$tmp,$angle,$adjust);
          return substr($text,$break+1);
        } else {
          // just split before the current character
          $tmp = substr($text,0,$i);
          $adjust=0;
          $ctmp=ord($text[$i]);
          if (isset($this->fonts[$cf]['differences'][$ctmp])){
            $ctmp=$this->fonts[$cf]['differences'][$ctmp];
          }
          $tmpw=($w-$this->fonts[$cf]['C'][$ctmp]['WX'])*$size/1000;
          $this->PRVTadjustWrapText($tmp,$tmpw,$width,$x,$adjust,$justification);
          // reset the text state
          $this->currentTextState = $store_currentTextState;
          $this->setCurrentFont();
          $this->addText($x,$y,$size,$tmp,$angle,$adjust);
          return substr($text,$i);
        }
      }
      if ($text[$i]=='-'){
        $break=$i;
        $breakWidth = $w*$size/1000;
      }
      if ($text[$i]==' '){
        $break=$i;
        $ctmp=ord($text[$i]);
        if (isset($this->fonts[$cf]['differences'][$ctmp])){
          $ctmp=$this->fonts[$cf]['differences'][$ctmp];
        }
        $breakWidth = ($w-$this->fonts[$cf]['C'][$ctmp]['WX'])*$size/1000;
      }
    }
  }
  // then there was no need to break this line
  if ($justification=='full'){
    $justification='left';
  }
  $adjust=0;
  $tmpw=$w*$size/1000;
  $this->PRVTadjustWrapText($text,$tmpw,$width,$x,$adjust,$justification);
  // reset the text state
  $this->currentTextState = $store_currentTextState;
  $this->setCurrentFont();
  $this->addText($x,$y,$size,$text,$angle,$adjust,$angle);
  return '';
}

// ------------------------------------------------------------------------------
function saveState($pageEnd=0){
  if ($pageEnd){
    // this will be called at a new page to return the state to what it was on the 
    // end of the previous page, before the stack was closed down
    // This is to get around not being able to have open 'q' across pages
    $opt = $this->stateStack[$pageEnd]; // ok to use this as stack starts numbering at 1
    $this->setColor($opt['col']['r'],$opt['col']['g'],$opt['col']['b'],1);
    $this->setStrokeColor($opt['str']['r'],$opt['str']['g'],$opt['str']['b'],1);
    $this->objects[$this->currentContents]['c'].="\n".$opt['lin'];
//    $this->currentLineStyle = $opt['lin'];
  } else {
    $this->nStateStack++;
    $this->stateStack[$this->nStateStack]=array(
      'col'=>$this->currentColour
     ,'str'=>$this->currentStrokeColour
     ,'lin'=>$this->currentLineStyle
    );
  }
  $this->objects[$this->currentContents]['c'].="\nq";
}

// ------------------------------------------------------------------------------

function restoreState($pageEnd=0){
  if (!$pageEnd){
    $n = $this->nStateStack;
    $this->currentColour = $this->stateStack[$n]['col'];
    $this->currentStrokeColour = $this->stateStack[$n]['str'];
    $this->objects[$this->currentContents]['c'].="\n".$this->stateStack[$n]['lin'];
    $this->currentLineStyle = $this->stateStack[$n]['lin'];
    unset($this->stateStack[$n]);
    $this->nStateStack--;
  }
  $this->objects[$this->currentContents]['c'].="\nQ";
}

// ------------------------------------------------------------------------------

function openObject(){
  // make a loose object, the output will go into this object, until it is closed, then will revert to
  // the current one.
  // this object will not appear until it is included within a page.
  // the function will return the object number
  $this->nStack++;
  $this->stack[$this->nStack]=$this->currentContents;
  // add a new object of the content type, to hold the data flow
  $this->numObj++;
  $this->o_contents($this->numObj,'new');
  $this->currentContents=$this->numObj;
  $this->looseObjects[$this->numObj]=1;
  
  return $this->numObj;
}

// ------------------------------------------------------------------------------

function reopenObject($id){
   $this->nStack++;
   $this->stack[$this->nStack]=$this->currentContents;
   $this->currentContents=$id;
}

// ------------------------------------------------------------------------------

function closeObject(){
  // close the object, as long as there was one open in the first place, which will be indicated by
  // an objectId on the stack.
  if ($this->nStack>0){
    $this->currentContents=$this->stack[$this->nStack];
    $this->nStack--;
    // easier to probably not worry about removing the old entries, they will be overwritten
    // if there are new ones.
  }
}

// ------------------------------------------------------------------------------

function stopObject($id){
  // if an object has been appearing on pages up to now, then stop it, this page will
  // be the last one that could contian it.
  if (isset($this->addLooseObjects[$id])){
    $this->addLooseObjects[$id]='';
  }
}

// ------------------------------------------------------------------------------

function addObject($id,$options='add'){
  // add the specified object to the page
  if (isset($this->looseObjects[$id]) && $this->currentContents!=$id){
    // then it is a valid object, and it is not being added to itself
    switch($options){
      case 'all':
        // then this object is to be added to this page (done in the next block) and 
        // all future new pages. 
        $this->addLooseObjects[$id]='all';
      case 'add':
        if (isset($this->objects[$this->currentContents]['onPage'])){
          // then the destination contents is the primary for the page
          // (though this object is actually added to that page)
          $this->o_page($this->objects[$this->currentContents]['onPage'],'content',$id);
        }
        break;
      case 'even':
        $this->addLooseObjects[$id]='even';
        $pageObjectId=$this->objects[$this->currentContents]['onPage'];
        if ($this->objects[$pageObjectId]['info']['pageNum']%2==0){
          $this->addObject($id); // hacky huh :)
        }
        break;
      case 'odd':
        $this->addLooseObjects[$id]='odd';
        $pageObjectId=$this->objects[$this->currentContents]['onPage'];
        if ($this->objects[$pageObjectId]['info']['pageNum']%2==1){
          $this->addObject($id); // hacky huh :)
        }
        break;
    }
  }
}

// ------------------------------------------------------------------------------

function addInfo($label,$value=0){
  // this will only work if the label is one of the valid ones.
  // modify this so that arrays can be passed as well.
  // if $label is an array then assume that it is key=>value pairs
  // else assume that they are both scalar, anything else will probably error
  if (is_array($label)){
    foreach ($label as $l=>$v){
      $this->o_info($this->infoObject,$l,$v);
    }
  } else {
    $this->o_info($this->infoObject,$label,$value);
  }
}

// ------------------------------------------------------------------------------

function setPreferences($label,$value=0){
  // this will only work if the label is one of the valid ones.
  if (is_array($label)){
    foreach ($label as $l=>$v){
      $this->o_catalog($this->catalogId,'viewerPreferences',array($l=>$v));
    }
  } else {
    $this->o_catalog($this->catalogId,'viewerPreferences',array($label=>$value));
  }
}

// ------------------------------------------------------------------------------

function addJpegFromFile($img,$x,$y,$w=0,$h=0){
  // attempt to add a jpeg image straight from a file, using no GD commands
  // note that this function is unable to operate on a remote file.

  if (!file_exists($img)){
    return;
  }

  $tmp=getimagesize($img);
  $imageWidth=$tmp[0];
  $imageHeight=$tmp[1];
    
  if ($w<=0 && $h<=0){
    return;
  }
  if ($w==0){
    $w=$h/$imageHeight*$imageWidth;
  }
  if ($h==0){
    $h=$w*$imageHeight/$imageWidth;
  }

  $fp=fopen($img,'rb');

  $tmp = get_magic_quotes_runtime();
  set_magic_quotes_runtime(0);
  $data = fread($fp,filesize($img));
  set_magic_quotes_runtime($tmp);
  
  fclose($fp);

  $this->addJpegImage_common($data,$x,$y,$w,$h,$imageWidth,$imageHeight);
}

// ------------------------------------------------------------------------------

function addImage(&$img,$x,$y,$w=0,$h=0,$quality=75){
  // add a new image into the current location, as an external object
  // add the image at $x,$y, and with width and height as defined by $w & $h
  
  // note that this will only work with full colour images and makes them jpg images for display
  // later versions could present lossless image formats if there is interest.
  
  // there seems to be some problem here in that images that have quality set above 75 do not appear
  // not too sure why this is, but in the meantime I have restricted this to 75.  
  if ($quality>75){
    $quality=75;
  }

  // if the width or height are set to zero, then set the other one based on keeping the image
  // height/width ratio the same, if they are both zero, then give up :)
  $imageWidth=imagesx($img);
  $imageHeight=imagesy($img);
  
  if ($w<=0 && $h<=0){
    return;
  }
  if ($w==0){
    $w=$h/$imageHeight*$imageWidth;
  }
  if ($h==0){
    $h=$w*$imageHeight/$imageWidth;
  }
  
  // gotta get the data out of the img..

  // so I write to a temp file, and then read it back.. soo ugly, my apologies.
  $tmpDir='/tmp';
  $tmpName=tempnam($tmpDir,'img');
  imagejpeg($img,$tmpName,$quality);
  $fp=fopen($tmpName,'rb');

  $tmp = get_magic_quotes_runtime();
  set_magic_quotes_runtime(0);
  $data = fread($fp,filesize($tmpName));
  set_magic_quotes_runtime($tmp);
  fclose($fp);
  unlink($tmpName);
  $this->addJpegImage_common($data,$x,$y,$w,$h,$imageWidth,$imageHeight);
}

// ------------------------------------------------------------------------------

function addJpegImage_common(&$data,$x,$y,$w=0,$h=0,$imageWidth,$imageHeight){
  // note that this function is not to be called externally
  // it is just the common code between the GD and the file options
  $this->numImages++;
  $im=$this->numImages;
  $label='I'.$im;
  $this->numObj++;
  $this->o_image($this->numObj,'new',array('label'=>$label,'data'=>$data,'iw'=>$imageWidth,'ih'=>$imageHeight));

  $this->objects[$this->currentContents]['c'].="\nq";
  $this->objects[$this->currentContents]['c'].="\n".$w." 0 0 ".$h." ".$x." ".$y." cm";
  $this->objects[$this->currentContents]['c'].="\n/".$label.' Do';
  $this->objects[$this->currentContents]['c'].="\nQ";
}

// ------------------------------------------------------------------------------

function openHere($style,$a=0,$b=0,$c=0){
  // this function will open the document at a specified page, in a specified style
  // the values for style, and the required paramters are:
  // 'XYZ'  left, top, zoom
  // 'Fit'
  // 'FitH' top
  // 'FitV' left
  // 'FitR' left,bottom,right
  // 'FitB'
  // 'FitBH' top
  // 'FitBV' left
  $this->numObj++;
  $this->o_destination($this->numObj,'new',array('page'=>$this->currentPage,'type'=>$style,'p1'=>$a,'p2'=>$b,'p3'=>$c));
  $id = $this->catalogId;
  $this->o_catalog($id,'openHere',$this->numObj);
}

// ------------------------------------------------------------------------------

function setFontFamily($family,$options=''){
  if (!is_array($options)){
    if ($family=='init'){
      // set the known family groups
      // these font families will be used to enable bold and italic markers to be included
      // within text streams. html forms will be used... <b></b> <i></i>
      $this->fontFamilies['Helvetica.afm']=array(
         'b'=>'Helvetica-Bold.afm'
        ,'i'=>'Helvetica-Oblique.afm'
        ,'bi'=>'Helvetica-BoldOblique.afm'
        ,'ib'=>'Helvetica-BoldOblique.afm'
      );
      $this->fontFamilies['Courier.afm']=array(
         'b'=>'Courier-Bold.afm'
        ,'i'=>'Courier-Oblique.afm'
        ,'bi'=>'Courier-BoldOblique.afm'
        ,'ib'=>'Courier-BoldOblique.afm'
      );
      $this->fontFamilies['Times-Roman.afm']=array(
         'b'=>'Times-Bold.afm'
        ,'i'=>'Times-Italic.afm'
        ,'bi'=>'Times-BoldItalic.afm'
        ,'ib'=>'Times-BoldItalic.afm'
      );
    }
  } else {
    // the user is trying to set a font family
    // note that this can also be used to set the base ones to something else
    if (strlen($family)){
      $this->fontFamilies[$family] = $options;
    }
  }
}

// ------------------------------------------------------------------------------

function addMessage($message){
  $this->messages.=$message."\n";
}

// ------------------------------------------------------------------------------

} // end of class

?>