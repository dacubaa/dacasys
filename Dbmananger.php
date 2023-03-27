<?php
namespace App\Models;
use DateTime;
// ini_set('memory_limit', '-1'); 
 // if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
*dacasystems
*dacubaa@hotmail.com
*esta version de dbmanager esta un poco adaptada e incompleta, contacte en caso de falla
*/


// $file =APPPATH.'/Models/Dbmjson/'.basename(__FILE__, '.php').'.json';
// if (file_exists($file)) {return  json_decode( file_get_contents($file), true);}



class Conexion{
    private $Server;
    private $User;
    private $Pass;
    private $Db;
    protected $link;
    protected $_resultSet;
    public $columns;
    // function __construct($S,$U,$C,$B){
    function __construct($S){
        $this->Server  = $S->hostname;
        $this->User  = $S->username;
        $this->Pass  = $S->password;
        $this->Db  = $S->database;
        $this->linktodb();
        $this->link->set_charset("utf8");
    }
    private function linktodb(){$this->link = mysqli_connect($this->Server,$this->User,$this->Pass,$this->Db)or die(mysqli_connect_errno());}
    protected function free(){
        $this->_resultSet->free();
        $this->_resultSet=null;
    }
    protected function checkMoreResults(){
        if($this->link->more_results()){
            return true;
        } else {
            return false;
        }
    }
    protected function clearResults(){
        if($this->checkMoreResults()){
            if($this->link->next_result()){
                if($this->_resultSet=$this->link->store_result()){
                    $this->free();
                }
                $this->clearResults();
            }
        }
    }
    public function query($sql){
        $GLOBALS["answer"][]["consola"]= $sql;
        $rows=array();

        $this->_resultSet = $this->link->query($sql);

        if (!is_bool($this->_resultSet)) {
            while($resultSet2 = $this->_resultSet->fetch_array(MYSQLI_ASSOC))
            {
                $rows[]=$resultSet2;
            }
            $this->columns = @array_keys($rows[0]);
            $this->clearResults();
            return $rows;
        }else{
            if ($this->_resultSet==true) {
                return $this->link->insert_id;
            } else {
            }
        }
    }
    function refValues($arr){
        if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
        {
            $refs = array();
            foreach($arr as $key => $value)
                $refs[$key] = &$arr[$key];
            return $refs;
        }
        return $arr;
    }
    public function prepare($sql,$rows){
        $rows=array();
        $this->_resultSet = $this->link->prepare($sql);
        return array_combine(array_map(function($k){ return ':'.$k; }, array_keys($rows)),$rows);
        call_user_func_array(array($this->_resultSet, 'bind_param'),  array_combine(array_map(function($k){ return ':'.$k; }, array_keys($rows)),$rows));

        /* ejecutar la consulta */
        $this->_resultSet->execute();

        if (!is_bool($this->_resultSet)) {
            while($resultSet2 = $this->_resultSet->fetch_array(MYSQLI_ASSOC))
            {
                $rows[]=$resultSet2;
            }
            $this->columns = @array_keys($rows[0]);
            $this->clearResults();
            return $rows;
        }else{
            if ($this->_resultSet==true) {
                return $this->link->insert_id;
            } else {
            }
        }
    }
    function __destruct(){
        $this->link->close();
        $this->link=null;
    }
}
class Dbmananger extends \CodeIgniter\Model   {
    private $dblink;
    private $Table;
    private $TFields;
    private $postquerys = [];
    public $foreignreferences;
    public $Fields;
    public $Primarykey;
    public  $where;
    public  $notwhere;
    public  $like;
    public  $orderby;
    public  $innerjoin;
    public  $leftjoin;
    public  $rigtjoin;
    private  $wheresq;
    public  $readwrite;
    public  $filefield;
    public  $limit;
    public  $totalreg;
    public  $lastpage;
    public  $between;
    public  $fieldstatus="id_status";
    public  $checkverificar=FALSE;
    public  $asktotal=true;
    public  $cunstomfields=[];
    public  $cunstomselect=[];
    private $Foreignkey=[];
    function __construct($t=null){
        $directory =WRITEPATH .'/Dbmjson';
        if (!file_exists($directory)) {mkdir($directory,  0755, 1);}
        if (file_exists($directory.'/cunstomfields.json')) {$this->cunstomfields = json_decode(file_get_contents($directory.'/cunstomfields.json'), true);}
        
        $this->Table = $t;
        $this->db =  \Config\Database::connect('default', false);
        $this->dblink = new Conexion($this->db);
        if ($t==null) {return false;}
        $exists = $this->ifexisttable();
        if($exists){
            $file =$directory.'/'.$t.'.json';
            if (file_exists($file)) {
                $datos = json_decode( file_get_contents($file), true);
                $this->Fields = $datos['Fields'] ;
                $this->TFields = $datos['TFields'] ;
                $this->Primarykey = $datos['Primarykey'] ;
         
                $this->readwrite = $datos['readwrite'] ;
            }else {
                $datos['Fields'] = $this->Fields = $this->obtenerncampos();
                $datos['TFields'] = $this->TFields = $this->tipocampos();
                $datos['Primarykey'] = $this->Primarykey = $this->claveprimaria();
                $datos['readwrite'] = $this->readwrite = $this->tipotabla();
                $newJsonString = json_encode($datos);
                file_put_contents($file, $newJsonString);
            }
            return $exists;
        }

    }
    function postquery(){
        if (count($this->postquerys)>=1) {
            # code...
            foreach ($this->postquerys as   $value) {
                $this->dblink->query($value);
            }
        }
    }
    function ifexistprocedure($Procedure=null){
        $resultado = $this->dblink->query("select SPECIFIC_NAME,ROUTINE_TYPE from  information_schema.routines where ROUTINE_SCHEMA like '". $this->db->database."' and ROUTINE_NAME = '".$Procedure."'");
        if (count($resultado)>0) {
            return TRUE;
        }else{
            return FALSE;
        }
    }
    function ifexisttable($table=null){
        $table = $this->Table??$table;
        $resultado = $this->dblink->query("SHOW full tables from ". $this->db->database." like  '$table'");
        if (count($resultado)>0) {
            return TRUE;
        }else{
            return FALSE;
        }
    }
    function claveprimaria(){
        $resultado = $this->dblink->query("SELECT column_name FROM information_schema.key_column_usage where TABLE_NAME =  '$this->Table'");
        return ( isset($resultado[0]['column_name']))?$resultado[0]['column_name']:"read only";
    }
    function tipotabla(){
        $resultado = $this->dblink->query("SHOW full tables from ". $this->db->database." like  '$this->Table'");
        if ($resultado[0]['Table_type']=='BASE TABLE') {
            return TRUE;
        }else{
            return FALSE;
        }
    }
    private function tipocampos(){
        return  $this->dblink->query("SHOW full COLUMNS FROM `".$this->Table."`");
    }
    private function jointabla($type){
        if ($this->ifexisttable()) {
        }
    }
    function claveforanea($Foreignkey = null){
        $value = array();
        $resultado = $this->dblink->query("select column_name as 'foreignkey', referenced_table_name as 'table', referenced_column_name as 'references' from information_schema.key_column_usage where referenced_table_name is not null and table_schema = '". $this->db->database."' and table_name = '".$this->Table."'");
        if (count($resultado)>0) {
            foreach ($resultado as $key) {
                @$value[$key['table']] = new DBmananger($key["table"]);
                @$value[$key['table']]->foreignreferences[$key["foreignkey"]] = $key['references'];
            }
        } else {
            if (is_array($Foreignkey) && count($Foreignkey)>0) {
                foreach ($Foreignkey as $ll => $key) {
                    if (array_key_exists("table",$key) && array_key_exists("foreignkey",$key) && array_key_exists("references",$key)) {
                        if($this->ifexisttable($key['table'])){
                            @$value[$key['table']] = new DBmananger($key["table"]);
                            @$value[$key['table']]->foreignreferences[$key["foreignkey"]] = $key['references'];
                        }
                    }
                }
            }
        }
        return $this->Foreignkey = $value;
    }
    function verificar(){
        if ($this->checkverificar) {
            $ar=TRUE;
            foreach ($this->TFields as $key) {if (!($key["Field"]==$this->Primarykey||!is_null($key['Default']))) {if(!(($key['Null']=="YES")||((!empty(@$this->where[$key['Field']]))))){$GLOBALS["answer"][]["alert"]="Por favor ingrese el campo ".$key['Field'];$ar=FALSE;};}}
            $resultado = $this->dblink->query("SELECT DATA_TYPE, COLUMN_NAME,IF(CHARACTER_MAXIMUM_LENGTH is null and NUMERIC_PRECISION is null ,50,  IF(NUMERIC_PRECISION is null,CHARACTER_MAXIMUM_LENGTH,NUMERIC_PRECISION)) as CHARACTER_MAXIMUM  FROM information_schema.COLUMNS where TABLE_NAME = '".$this->Table."'");
            foreach ($resultado as $key) {if (@strlen($this->where[$key['COLUMN_NAME']])>$key['CHARACTER_MAXIMUM']){$GLOBALS["answer"][]["alert"]="el campo ".$key['COLUMN_NAME']." excede el limite de caracteres";$ar=FALSE;};};
            foreach ($resultado as $key) {
                switch ($key['DATA_TYPE']) {
                    case 'date':
                    if ($key['DATA_TYPE']=='date') {$formato='Y-m-d';}
                    case 'datetime':
                    if ($key['DATA_TYPE']=='datetime') {$formato='Y-m-d';}
                    case 'time':
                    if ($key['DATA_TYPE']=='time') {$formato='H:i';}
                    case 'timestamp':
                    if ($key['DATA_TYPE']=='timestamp') {$formato='Y-m-d H:i:s';$this->where[$key['COLUMN_NAME']] = str_replace("T", " ", $this->where[$key['COLUMN_NAME']]);}
                    case 'year':
                    if ($key['DATA_TYPE']=='year') {$formato='Y';}
                    $d = DateTime::createFromFormat($formato,$this->where[$key['COLUMN_NAME']]);
                    if (!( $d && $d->format($formato) === $this->where[$key['COLUMN_NAME']])&&!(is_null(@$this->where[$key['COLUMN_NAME']]))) { $GLOBALS["answer"][]["alert"]="el campo ".$key['COLUMN_NAME']." no es ".$key['DATA_TYPE'].".";return false;}
                    break;
                    case 'int':
                    case 'smallint':
                    case 'tinyint':
                    case 'mediumint':
                    case 'bigint':
                    case 'decimal':
                    case 'double':
                    case 'bit':
                    if (!(is_numeric(@$this->where[$key['COLUMN_NAME']]))&&!(is_null(@$this->where[$key['COLUMN_NAME']]))) { $GLOBALS["answer"][]["alert"]="el campo ".$key['COLUMN_NAME']." no es ".$key['DATA_TYPE'].".";return false;}
                    break;
                    case 'tinyblob':
                    case 'blob':
                    case 'mediumblob':
                    case 'longblob':
                    return true;
                    break;
                    case 'char':
                    case 'varchar':
                    case 'set':
                    case 'enum':
                    case 'tinytext':
                    case 'text':
                    case 'mediumtext':
                    case 'longtext':
                    if (!(is_string(@$this->where[$key['COLUMN_NAME']]))&&!(is_null(@$this->where[$key['COLUMN_NAME']]))) { $GLOBALS["answer"][]["alert"]="el campo ".$key['COLUMN_NAME']." no es ".$key['DATA_TYPE'].".";return false;}
                    break;
                    default:
                    $GLOBALS["answer"][]["alert"]=$key['DATA_TYPE']." => ".$key['COLUMN_NAME'] ;
                    break;
                }
            };
            return $ar;
        }else{ return true;}
    }
    function obtenerncampos(){
        $resultado = $this->dblink->query("SHOW full COLUMNS FROM $this->Table");
        if (count($resultado) > 0) {
            $ar  = array();
            foreach ($resultado as $key) {$ar[$key['Field']] = $key['Field'];}
            return $ar;
        }else {
        }
    }
    
    function limitquery($pag){
        $where = $this->wherequery();
        $where = ($where !=NULL && $where != "") ?"where ".$where:"";
        if(!empty($this->limit)){
            if (count($this->Foreignkey)>0) {
                $key =key($this->Foreignkey);
                $references =key($this->Foreignkey[$key]->foreignreferences);
                $references2 =$this->Foreignkey[$key]->foreignreferences[$references];
                $limite = $this->dblink->query("SELECT DISTINCT IF(COUNT(DISTINCT ".$references.")%".$this->limit." >0,TRUNCATE(COUNT(DISTINCT ".$references.")/".$this->limit.",0)+1,COUNT(DISTINCT ".$references.")/".$this->limit.") as lastpage, count(DISTINCT ".$references.") as total FROM ".$this->Table." inner join ".$key." on ".$references2." = ".$references." ".$where);
            }else {
                if ($this->asktotal == true) {
                    $limite = $this->dblink->query("SELECT IF(COUNT(*)%".$this->limit." >0,TRUNCATE(COUNT(*)/".$this->limit.",0)+1,COUNT(*)/".$this->limit.") as lastpage, count(*) as total FROM  ".$this->Table." ".$where);
                }
            }
            $limit = "limit ".(($this->asktotal == true)?($this->limit*(((($pag<1?1:$pag) >= $limite[0]["lastpage"])?$limite[0]["lastpage"]:($pag<1?1:$pag))-1)):0).",".$this->limit;
            if ($this->asktotal == true) {
                $this->lastpage=round($limite[0]["lastpage"],0);
                $this->totalreg=round($limite[0]["total"],0);
            }
        }else{$limit = "";}
        return $limit;
    }
    function likequery(){

        return   "";
    }
    function betweenquery() {
        $array = array();$valor = array();
        if (is_array($this->between) && count($this->between) > 0) {foreach ($this->between as $key => $value) {if (in_array($key, $this->Fields) && (count($value) == 2)) {$array[$key] = $value;}}}
        foreach ($array as $key => $val) {
            $valor[] =  "`".$key."` BETWEEN '" . $val[0] . "' and '" . $val[1] . "'";
        }
        return $valor;
    }
    function orderbyquery(){
        $array = array();$valor =array();
        if (is_array($this->orderby)&&count($this->orderby)>0) {
            foreach ($this->orderby as $key => $value) {if (in_array($key, $this->Fields)&&(isset($value))) {$valor[]= $key.' '.$value;}}
            return " order by ".implode(" , ",$valor);
        }
        return "";
    }
    function notwherequery($prepare=false){
        $array = array();$valor =array();
        if (is_array($this->notwhere)&&count($this->notwhere)>0) {foreach ($this->notwhere as $key => $value) {if (in_array($key, $this->Fields)&&(isset($value))) {$array[$key]=$value;}}}
        if ($prepare) {
            foreach ($array as $key => $val ) {$valor[] = "`".$key."`!=':".$key."'";	}//pendiente en prepare
        }else{
            foreach ($array as $key => $val ) {$valor[] = (is_array($val))?  "(`".$key."`!='".implode("' and `".$key."`!='",$val)."')": "`".$key."`!='".$val."'";		}
        }
        return  $valor;
    }
    function wherequery($prepare=false){
        $array = array();$valor =array();
        if (@in_array($this->fieldstatus,$this->Fields)) {$array[$this->fieldstatus]= "1";}
        if (is_array($this->where)&&count($this->where)>0) {foreach ($this->where as $key => $value) {if (in_array($key, $this->Fields)&&(isset($value))) {$array[$key]=$value;}}}
        if ($prepare) {
            foreach ($array as $key => $val ) {$valor[] = "`".$key."`=':".$key."'";	}//pendiente en prepare
        }else{
            foreach ($array as $key => $val ) {$valor[] =(is_array($val))?  "(`".$key."`='".implode("' or `".$key."`='",$val)."')": "`".$key."`='".$val."'";	}
        }
        return  implode(" and ",array_merge($valor,$this->notwherequery($prepare),$this->betweenquery())).$this->likequery();
    }
    function select_fields(){
        $array = array();$valor =array();
        if (count( $this->cunstomselect) >0) {
        foreach ($this->cunstomselect as $key => $value) {
            if (in_array($key, $this->Fields) ) {
                $texto =  explode('[['.$key.']]',  '_[['.$key.']]'.$value);
                $array[$key] =  "concat('".$texto[1] ."',".$key.",'".($texto[2]??"")."') as ".$key;
            }else{
                $array[$key] = "(".$value.") as ".$key;
                
            }
        }
        return implode( ", ",array_merge( $this->Fields,$array));
        }else return "*";
    }

    function obtenerTabla($pag=0){
        $pag=(is_int($pag))?$pag:0;
        $arra="";
        $where = $this->wherequery();
        $orderby = $this->orderbyquery();
        $where = ($where !=NULL && $where != "") ?"where ".$where:"";
        $where = ($orderby !=NULL && $orderby != "") ?$where.$orderby:$where;
        $limit=$this->limitquery($pag);
        if (count($this->Foreignkey)>0) {
            $key =key($this->Foreignkey);
            $references =key($this->Foreignkey[$key]->foreignreferences);
            $references2 =$this->Foreignkey[$key]->foreignreferences[$references];
            $resultado = $this->dblink->query("SELECT DISTINCT ".$this->Table.".".$this->select_fields()." FROM ".$this->Table." inner join ".$key." on ".$references2." = ".$references." ".$where." ".$limit);
            $where2 = ($this->Foreignkey[$key]->wherequery()!=NULL) ? "where ".$this->Foreignkey[$key]->wherequery():"";
            $resultado2 = $this->dblink->query("SELECT ".$key.".".$this->Foreignkey[$key]->select_fields()." FROM ".$key." inner join (SELECT DISTINCT ".$references." FROM ".$this->Table." inner join ".$key." on ".$references2." = ".$references." ".$where." ".$limit.") as b on ".$references." = ".$references2." ".$where2);
            if (count($resultado2) == 1 && count($resultado) == 1) {
                $resultado[0][$key] =$resultado2[0];
            }else {
                foreach ($resultado as $k => $v) {
                    foreach ($resultado2 as $k2 => $v2) {
                        if ($resultado[$k][$references]==$resultado2[$k2][$references2]) {
                            $resultado[$k][$key][]=$resultado2[$k2];
                        }
                    }
                }
            }
        }else {
            $resultado = $this->dblink->query("SELECT ".$this->select_fields()." FROM $this->Table $where $limit");

        }
        return $resultado;
    }
// i	la variable correspondiente es de tipo entero
// d	la variable correspondiente es de tipo double
// s	la variable correspondiente es de tipo string
// b	la variable correspondiente es un blob y se envía en paquetes
    function obtenerPrepare($pag=0){
        $pag=(is_int($pag))?$pag:0;
        $arra="";
        $where = $this->wherequery();
        $orderby = $this->orderbyquery();
        $where = ($where !=NULL && $where != "") ?"where ".$where:"";
        $where = ($orderby !=NULL && $orderby != "") ?$where.$orderby:$where;
        $limit=$this->limitquery($pag);
        $resultado = $this->dblink->prepare("SELECT ".$this->select_fields()." FROM $this->Table $where $limit",$this->where);
        return $resultado;
    }
    function obtenerCall($procedure,$arg=null){
        if(is_array($arg)){if(count($arg)==0){$arg=null;}}else{if(empty($arg)){$arg=null;}else{$arg[0]=$arg;}}
        if ($this->ifexistprocedure($procedure)) {
        $resultado = $this->dblink->query("call $procedure(".(($arg!=null)?"'".implode("','",$arg)."'":"").")");
        return $resultado;
        }
    }
    function eliminar(){
        if ($this->readwrite) {
            if (!empty($this->where[$this->Primarykey])) {
                $where = $this->wherequery();
                $sql=((in_array($this->fieldstatus,$this->Fields))?"UPDATE $this->Table SET $this->fieldstatus ='0' WHERE $where":"DELETE FROM $this->Table WHERE  $where");
                $this->dblink->query($sql);
            } else {
                $GLOBALS["answer"][]["alert"]="Debe especificar un valor";
            }
        } else {
        }
    }
    function modificar(){
        if ($this->readwrite) {
            if ($this->verificar()) {
                $campos = $this->Fields;
                $resultado = array();
                $a=0;
                $detalles ="no data";
                foreach ($this->where as $key => $value) {
                    if ($key != "$this->Primarykey"){
                        if ($detalles =='no data') {
                            $detalles = $key." = '".$value."'";
                        }else {
                            $detalles = $detalles .", ". $key." = '".$value."'";
                        }
                    }else {
                        $where = $key." = '".$value."'";
                    }
                }
                return $this->dblink->query("UPDATE $this->Table SET $detalles WHERE $where");
            }
        } else {
        }
    }
    function insertarnomodificar(){
        $this->insertarmodificar(false);
    }
    function insertarmodificar($modificar=true){
		if ($this->readwrite) {
			if ($this->verificar()) {
				$campos = $this->Fields;$dbdatos="";$i=0;
				foreach ($campos as $key) {
					if (@in_array("files", array_keys($this->where[$key]))) {$this->where[$key]=$this->savefile($key);}
					if(isset($this->where[$key])){$e=($i>0) ?",":"";$dbdatos .= (isset($this->where[$key])) ?((@in_array($key, array_keys($this->wheresq))&&$this->wheresq[$key]==true)?$e."(".$this->where[$key].")": $e."'".$this->where[$key]."'"):$e."NULL";$array_keys[$key]=$key;$i++;}else{unset($this->where[$key]);}
				}
				$detalles ="no data";
				foreach ($this->where as $key => $value) {
                    if (in_array($key,$campos)){
                    if ($key != "$this->Primarykey"){
                        if ($detalles =='no data') {
                            $detalles = $key." = '".$value."'";
						}else {
                            $detalles = $detalles .", ". $key." = '".$value."'";
						}
					}else {
                        $where = $key." = '".$value."'";
					}
					}
				}
				$resultado = $this->dblink->query("INSERT INTO ".$this->Table." (`".implode("`,`",$array_keys)."`) VALUES (".$dbdatos.") ON DUPLICATE KEY UPDATE ".(($modificar)?$detalles : $where?? $this->Primarykey." = '0'"));
				if ($resultado != "0" ) {$this->where[$this->Primarykey] = $resultado;}
				$this->postquery();
				return $this->where[$this->Primarykey];
			}
		} else {
		}
	}
    function insertar(){
        if ($this->readwrite) {
            if ($this->verificar()) {
                $campos = $this->Fields;$dbdatos="";$i=0;$array_keys=[];
                foreach ($campos as $key) {
                    if (@in_array("files", array_keys($this->where[$key]))) {$this->where[$key]=$this->savefile($key);}
                    if(isset($this->where[$key])){
                        $e=($i>0) ?",":"";
                        $dbdatos .= (isset($this->where[$key])) ?((@in_array($key, array_keys($this->wheresq))&&$this->wheresq[$key]==true)?$e."(".$this->where[$key].")": $e."'".$this->where[$key]."'"):$e."NULL";
                        $array_keys[$key]=$key;
                        $i++;
                    }else{
                        unset($this->where[$key]);
                    }
                }
                $this->where[$this->Primarykey] = $this->dblink->query("INSERT INTO ".$this->Table." (`".implode("`,`",$array_keys)."`) VALUES (".$dbdatos.")");
                $this->postquery();
                return $this->where[$this->Primarykey];
            }
        } else {
        }
    }
    function generarcampos($type,$Aclass,$readonly, $campos =null,$objectdata=[]){
        $inget = ($campos!=null)?$campos:$this->where;
        $formulario=  array();
        $campos =($campos!=null)?array_keys($campos):$this->Fields;
        foreach ($campos as $k => $v){
            $name = $v;
            $value =(isset($inget[$v]))?$inget[$v]:"";
            $class =(is_array($Aclass))?(!empty($Aclass[$v])?$Aclass[$v]:""):(!empty($Aclass)?$Aclass:"");
            $datos =array_merge( ["class"=>$class,"name"=>$name],$objectdata[$v]??[]);
            $datos =array_merge( array_map( function(&$a) {return $a =(!is_array($a))?$a:"";},$datos),["value"=>$value]);
            $datosllaves =array_combine(array_keys($datos),array_map(function($k){ return '__'.$k.'__'; }, array_keys($datos)) );
            $case = (is_array($type))?((isset($type[$v]))? $type[$v]:"text"):$type;
            switch ($case){
                case 'none':$formulario[$v] = "\n";break;
                case 'NULL':$formulario[$v] = ["0"=>["INPUT"=>["text"=>["0"=>"" ],"type"=>["hidden"],"name"=>[$name],"value"=>["NULL"] ] ] ];break;
                case 'hidden':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["INPUT"=>["text"=>["0"=>"" ],"type"=>["hidden"],"name"=>[$name],"id"=>[$name],"value"=>[$value] ] ] ] ] ] ];break;
				case 'only_value':$formulario[$v] = $value;
                case 'p':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["P"=>["text"=>["0"=>$value ] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'label':$formulario[$v] = ["0"=>["LABEL"=>["text"=>["0"=>$name ] ] ] ];break;
                case 'divimg':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["IMG"=>["text"=>["0"=>"" ],"src"=>[$value],"alt"=>["sample"] ] ] ],"name"=>[$name],"id"=>[$name],"class"=>explode(" ",$class) ] ] ];break;
                case 'img':$formulario[$v] = ["0"=>["IMG"=>["text"=>["0"=>"" ],"src"=>[$value],"id"=>[$name],"class"=>explode(" ",$class),"alt"=>["sample"] ] ] ];break;
                case 'url':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["url"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'email':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["email"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'number':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["number"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'range':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"min"=>["0"],"max"=>["100"],"type"=>["range"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'date':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name],"class"=>["active"] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["date"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'week':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["week"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'time':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["time"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'datetime-local':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["datetime-local"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'color':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["color"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'file':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["DIV"=>["apend"=>["0"=>["SPAN"=>["text"=>["0"=>"" ],"class"=>["mdi-file-file-upload"] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"xfile"=>[""],"id"=>[$name],"type"=>["file"] ] ] ],"class"=>["btn"] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'multifile':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["DIV"=>["apend"=>["0"=>["SPAN"=>["text"=>["0"=>"" ],"class"=>["mdi-file-file-upload"] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"multiple"=>[""],"xfile"=>[""],"id"=>[$name],"type"=>["file"] ] ] ],"class"=>["btn"] ] ],"1"=>["DIV"=>["apend"=>["0"=>["INPUT"=>["text"=>["0"=>"" ],"class"=>["file-path","validate","valid"],"type"=>["text"] ] ] ],"class"=>["file-path-wrapper"] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'password':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"class"=>explode(" ",$class),"id"=>[$name],"type"=>["password"],"value"=>[$value],"readonly"=>[""],"placeholder"=>["undefined"] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'passwordx2':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"class"=>explode(" ",$class),"id"=>[$name],"type"=>["password"],"value"=>[$value],"readonly"=>[""],"placeholder"=>["undefined"] ] ],"2"=>["LABEL"=>["text"=>["0"=>"Repetir undefined" ] ] ],"3"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>["rundefined"],"class"=>explode(" ",$class),"id"=>["Repetir_undefined"],"type"=>["password"],"value"=>[$value],"readonly"=>[""],"placeholder"=>["undefined"] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
                case 'text':$formulario[$v] = (array_key_exists("text",$this->cunstomfields))?json_decode(@str_replace($datosllaves, $datos,json_encode($this->cunstomfields["text"]))) :["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["text"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
				// case 'text_bd':$formuldario[$v] = "<div class='".$class."'>".$title. "<input class='browser-default' name='".$name."'   id='".$name."'  type='text' value='".$value."' ".$atributo."  ></div>";break;
                case 'textarea':$formulario[$v] = ["0"=>["LABEL"=>["text"=>["0"=>$name ] ] ] ];break;
                case 'checkbox':$formulario[$v] = ["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"class"=>explode(" ",$class),"id"=>[$name],"type"=>["checkbox"], (($value==1)?"checked":"")=>[""], ($readonly=="readonly")?"disabled":""=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ] ;break;
                case 'table':
                    if ($name==key($this->Foreignkey)) {
                        $ffield = $this->Foreignkey[$name]->obtenerncampos();
                        $this->Foreignkey[$name]->where[$this->Foreignkey[$name]->Primarykey] = $value;
                        $tbody="";
                        foreach ($this->Foreignkey[$name]->obtenerTabla() as $key) {
                            $tbody.="<tr>";
                            foreach ($ffield as  $head) {
                                $tbody.="<td name='".$name."'  class='".$class."' id='".$name."' >".$key[$head]."</td>";
                            }	$tbody.="</tr>";
                        }
                        $formulario[$v] = "<label>$name</label><table name='table-".$name."' id='table-".$name."' class='table-".$class."'>".$tbody."</table>";
                    } else {
                        Echo"error, el campo no tiene clave foranea";
                    }
                break;
                case 'select':if ($name==key($this->Foreignkey)) {$ffield = $this->Foreignkey[$name]->obtenerncampos();$option="";foreach ($this->Foreignkey[$name]->obtenerTabla() as $key) {$option.="<option value='".$key[$ffield[0]]."' ".(($value==$key[$ffield[0]])?"selected":"").">".$key[$ffield[1]]."</option>";}$formulario[$v] = "<label>$name</label><select name='".$name."' id='".$name."' class='".$class."'>".$option."</select>";} else {Echo"error, el campo no tiene clave foranea";}break;
                case 'fieldset':'legend';break;
                case 'Submit':break;
                case 'Reset':break;
                case 'Button':break;
                case 'canvas':break;
                case 'svg':break;
                case 'audio':break;
                case 'microphone':break;
                case 'embed':break;
                case 'source':break;
                case 'track':break;
                case 'video':break;
                case 'iframe':break;
                case 'details':'summary';break;
                case 'radio':
                    if (array_key_exists($case, $this->cunstomfields)) {
                        $option = [];
                        foreach ($value as $ke => $val) {
                            $dll= array_combine(array_keys($val),array_map(function($k){ return '__'.$k.'__'; }, array_keys($val)) );
                            $option[] = json_decode(@str_replace(array_merge($datosllaves,$dll),array_merge($datos,$val), json_encode($this->cunstomfields["radio"])))[0];
                        }

                    } else {
                        echo "error, el campo no tiene clave foranea";
                    }
                $formulario[$v] =  $option;
                break;
                default:$formulario[$v] = (array_key_exists($case,$this->cunstomfields))?json_decode(@str_replace($datosllaves, $datos,json_encode($this->cunstomfields[$case]))) :["0"=>["DIV"=>["apend"=>["0"=>["LABEL"=>["text"=>["0"=>$name ],"for"=>[$name] ] ],"1"=>["INPUT"=>["text"=>["0"=>"" ],"name"=>[$name],"id"=>[$name],"type"=>["text"],"value"=>[$value],"readonly"=>[""] ] ] ],"class"=>explode(" ",$class) ] ] ];break;
            }
        }
        return $formulario;
    }
    function savefile($x){
        foreach ($this->TFields as $key => $value) {
            if ($value["Field"]==$x) {
                if ($value["Type"]=="longblob"||$value["Type"]=='tinyblob'||$value["Type"]=='blob'||$value["Type"]=='mediumblob'||$value["Type"]=='longblob') {
                    if (count($this->where[$x]["files"])==1) {
                        $this->postquerys[]='call move_file("'.$this->Table.'","'.$x.'","'.$_SESSION["filesfiels"][$this->where[$x]["files"][0]].'");';
                        return $_SESSION["filesfiels"][$this->where[$x]["files"][0]];
                    } else {
                        return '';
                    }
                }else{
                }
            }
        }
    }
}
