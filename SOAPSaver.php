<?php

class objSOAPTypes implements ArrayAccess, Iterator {
	private $xmlDoc = NULL;

	private $enumeratedTypes = array(); //validation for enumerated value types
	private $variable = array();
	private $elements = array();  //validation for when this object functions as an array
	private $parameters = array();  //validation for parameters in XML tag
	private $children = array();  //validation for child elements (xml or SOAP)

	private $pdata = array();  //data holder for parameter data
	private $cdata = array();  //data holder for child data
	private $vdata = NULL; //data holder for single value: i.e. Text or a number (this class acts as a (sort of) type with validation
	private $adata = array(); //data holder for when this object functions as an array
	private $position = 0;

	private $validatedValues = TRUE;
	private $validatedRequired = TRUE;
	private $validationErrors = array();

	public function __construct() {
		$numargs = func_num_args();
		$this->position = 0; //set array pointer to 0 element
		if($numargs > 1) $this->adata = func_get_args(); //Array Constructo behavior i.e. $arr = new objSOAPTypes("a","b","c");
		elseif($numargs == 1) $this->vdata = func_get_arg(0); //Set variable data on instantiation
	}

	//Overloaded set and get methods
	//------------------------------
	public function __set($name, $value) {
		if(in_array($name, array_keys($this->parameters))) $this->pdata[$name] = $value;
		elseif(in_array($name, array_keys($this->children))) $this->cdata[$name] = $value;
		else return FALSE;
	}

	public function __get($name) {
		if(in_array($name, array_keys($this->parameters))) return $this->pdata[$name];
		elseif(in_array($name, array_keys($this->children))) return $this->cdata[$name];
		else return NULL;

	}
	//end overloaded set and get methods
	//-------------------------------

	public function __toString() {
		if(isset($this->vdata)) return $this->vdata;
		else return "";
	}

	//ArrayAccess Interface methods
	//-----------------------------
	public function offsetSet($offset, $value) {
		if(is_null($offset)) $this->adata[] = $value;
		else $this->adata[$offset] = $value;
	}

	public function offsetExists($offset) {
		return isset($this->adata[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->adata[$offset]);
	}

	public function offsetGet($offset) {
		return isset($this->adata[$offset]) ? $this->adata[$offset] : null;
	}
	//end ArrayAccess Interface methods
	//-------------------------------

	//Iterator Interface methods
	//--------------------------
	public function rewind() {
		$this->position = 0;
	}

	public function current() {
		return $this->adata[$this->position];
	}

	public function key() {
		return $this->position;
	}

	public function next() {
		++$this->position;
	}

	public function valid() {
		return isset($this->adata[$this->position]);
	}
	//end Iterator Interface methods
	//-------------------------

	//set validation arrays:
	// format:
	// array("variable_name1" => array("required" => 0, "nillable" => 0, "type" => "variabletype1"),...)
	public function defineEnumeratedTypes($arr) {
		$this->enumeratedTypes = $arr;
	}

	public function setChildren($childArray) {
		$this->children = $childArray;
	}

	public function setParameters($paramArray) {
		$this->parameters = $paramArray;
	}

	public function setElements($arrayArray) {
		$this->elements = $arrayArray;
	}
	//-----------------------------------

	//These routines return sets of data descriptions
	//--------------------------------------------
	public function getRequiredParameters() {
		$required = array();
		foreach($this->parameters as $paramName => $paramValues) {
			if($paramValues["required"]) {
				$required[] = $paramName;
			}
		}
		return $required;
	}

	public function getRequiredElements() {
		$required = array();
		foreach($this->elements as $elementName => $elementValues) {
			if($elementValues["required"]) {
				$required[] = $paramName;
			}
		}
		return $required;
	}

	public function getRequiredChildren() {
		$required = array();
		foreach($this->children as $childName => $childValues) {
			if($childValues["required"]) {
				$required[] = $childName;
			}
		}
		return $required;
	}

	public function getAllChildren() {
		$all = array();
		foreach($this->children as $childName => $childValues) {
			$all[$childName] = $childValues["type"];
		}
		return $all;
	}

	public function isNillable($childName) {
		return $this->children[$childName]["nillable"];
	}

	public function isRequired($childName) {
		return $this->children[$childName]["required"];
	}
	//-------------------------------------

	private function decimal($val, $precision = 2) {
		if ((float) $val) {
        		$val = round((float) $val, (int) $precision);
        		list($a, $b) = explode('.', $val);
        		if (strlen($b) < $precision) $b = str_pad($b, $precision, '0', STR_PAD_RIGHT);
        		return $precision ? "$a.$b" : $a;
		}
   		else {
        		return 0.0;
		}
	}

	//Validation/conformance routines: these take raw data and turn it into something the remote server will accept
	//These routines use the variables, elements, parameters, and children validation arrays to determine what appropriate data is
	public function conformComplexSOAPType($objectName) {
		if(isset($this->vdata)) {
			$type = $this->variables["data"]['type'];
			$name = array_keys($this->variables);
			$name = $name[0];
			$value = $this->vdata;
			if(is_int(strripos($type, "Type")))  {
				$this->vdata = $this->conformSimpleSOAPType($name, $value, $type, $this->variables);
			}
			elseif(in_array($type, array("Int", "String", "Decimal", "Boolean", "DateTime", "TimeStamp", "Y-m-d"))) {
				$this->vdata = $this->conformValueType($name, $value, $type, $this->variables);
			}
		}
		elseif(count($this->adata) > 0) {
			foreach($this->elements as $name => $validation) {
				$type = $validation["type"];
				$value = $this->adata;
				$this->adata = $this->conformList($name, $value, $type, $this->elements);
			}
		}
		else {
			foreach($this->pdata as $name => $value) {
				$type = $this->parameters[$name]['type'];
				if(is_int(strripos($type, "Type")))  {
					$this->pdata[$name] = $this->conformSimpleSOAPType($name, $value, $type, $this->parameters);
				}
				elseif(in_array($type, array("Int", "String", "Decimal", "Boolean", "DateTime", "TimeStamp", "Y-m-d"))) {
					$this->pdata[$name] = $this->conformValueType($name, $value, $type, $this->parameters);
				}
				else {
					$this->pdata[$name] = NULL;
				}
			}
			foreach($this->cdata as $name => $value) {
				$type = $this->children[$name]['type'];
				if(is_int(strripos($type, "ArrayOf"))) {
					$type = substr($type, 7);
					$this->cdata[$name] = $this->conformArrayOf($name, $value, $type, $this->children);
				}
				elseif(is_int(strripos($type, "Type")))  {
					$this->cdata[$name] = $this->conformSimpleSOAPType($name, $value, $type, $this->children);
				}
				elseif(in_array($type, array("Int", "String", "Decimal", "Boolean", "DateTime", "TimeStamp", "Y-m-d"))) {
					$this->cdata[$name] = $this->conformValueType($name, $value, $type, $this->children);
				}
				elseif(method_exists($value, "conformComplexSOAPType")) {
					$this->cdata[$name] = $value->conformComplexSOAPType($name);
					$this->validationErrors[$name."(".get_class($this).")"] = $value->getValidationErrors();
				}
				else {
					$this->cdata[$name] = NULL;
				}
				if($this->children[$name]['xml'] === TRUE) {
					$this->cdata[$name] = $value->createXMLString($name);

				}
			}
		}
		$flag = FALSE;
		$missingFields = array();
		foreach($this->getRequiredChildren() as $index => $name) {
			if(!isset($this->cdata[$name])) {
				$missingFields[] = $name;
				$flag = TRUE;
			}
		}
		foreach($this->getRequiredParameters() as $index => $name) {
			if(!isset($this->pdata[$name])) {
				$missingParameters[] = $name;
				$flag = TRUE;
			}
		}

		if($flag) {
			$this->validationErrors["FATAL"] = $objectName." MISSING FIELDS: ".implode(",", $missingFields);
			return NULL;
		}
		return $this;
	}

	private function conformArrayOf($name, $array, $type, $info) {
		$temparray = array();
		$newarray = array();
		if(is_array($array)) {
			foreach($array as $index => $data) {
				if(in_array($type, array("Int", "String", "Decimal", "Boolean", "DateTime", "TimeStamp", "Y-m-d"))) {
					$temparray[$index] = $this->conformValueType($name, $data, $type, $info);
					if(isset($temparray[$index])) $newarray[] = $temparray[$index];
				}
				elseif(is_int(strripos($type, "Type"))) {
					$temparray[$index] = $this->conformSimpleSOAPType($name, $data, $type, $info);
					if(isset($temparray[$index])) $newarray[] = $temparray[$index];
				}
				elseif(method_exists($data, "conformComplexSOAPType")) {
					$temparray[$index] = $data->conformComplexSOAPType($name.$index);
					if(isset($temparray[$index])) $newarray[] = $temparray[$index];
					$this->validationErrors[$name.$index] = $data->getValidationErrors();
				}
				else $this->validationErrors[$name."(".$index.")".$type] = "is not a known type";
			}
			return $newarray;
		}
		elseif($this->children[$name]['nillable']) return array();
		$this->validationErrors[$name."(".$type.")"] = "must contain at least one value";
		return NULL;
	}

	private function conformList($name, $array, $type, $info) {
		$temparray = new $type();
		$newarray = new $type();
		if(is_array($array)) {
			foreach($array as $index => $data) {
				if(in_array($type, array("Int", "String", "Decimal", "Boolean", "DateTime", "TimeStamp", "Y-m-d"))) {
					$temparray[$index] = $this->conformValueType($name, $data, $type, $info);
					if(isset($temparray[$index])) $newarray[] = $temparray[$index];
				}
				elseif(is_int(strripos($type, "Type"))) {
					$temparray[$index] = $this->conformSimpleSOAPType($name, $data, $type, $info);
					if(isset($temparray[$index])) $newarray[] = $temparray[$index];
				}
				elseif(method_exists($data, "conformComplexSOAPType")) {
					$temparray[$index] = $data->conformComplexSOAPType($name.$index);
					if(isset($temparray[$index])) $newarray[] = $temparray[$index];
					$this->validationErrors[$name.$index] = $data->getValidationErrors();
				}
				else $this->validationErrors[$name."(".$index.")".$type] = "is not a known type";
			}
			return $newarray;
		}
		elseif($this->children[$name]['nillable']) return new $type();
		$this->validationErrors[$name."(".$type.")"] = "must contain at least one value";
		return NULL;
	}

	private function conformSimpleSOAPType($name, $data, $type, $info) {
		$enumeratedType = $this->enumeratedTypes[$type];
		foreach($enumeratedType as $rgValue => $validValue) {
			if($validValue == $data) {
				return $validValue;
			}
			elseif($rgValue == $data) {
				return $validValue;
			}
			elseif($rgValue == "CATCHALL") {
				return $validValue;
			}
		}
		if($info[$name]['nillable']) return "";
		$this->validationErrors[$name."(".$type.")"] = $data." is not in: (".implode(",",array_values($enumeratedType)).")";
		return NULL;
	}

	private function conformValueType($name, $data, $type, $info) {
		switch ($type) {
			case "Int":
				$data = intval($data);
				if(isset($info[$name]['range'])) {
					if(($data > $info[$name]['range']['min']) && ($data < $info[$name]['range']['max'])) return $data;
				}
				elseif($data != 0) return $data;
				elseif($info[$name]['nillable']) return $data;
				break;
			case "String":
				$data = (string) $data;
				if($data != "") {
					if(isset($info[$name]['regex'])) {
						if(preg_match($info[$name]['regex'], $data)) return $data;
					}
					else return $data;
				}
				elseif($info[$name]['nillable']) return $data;
				break;
			case "Decimal":
				$data = $this->decimal($data);
				if(isset($info[$name]['range'])) {
					if(($data > $info[$name]['range']['min']) && ($data < $info[$name]['range']['max'])) return $data;
				}
				elseif($data != 0.0) return $data;
				elseif($info[$name]['nillable']) return $data;
				break;
			case "Boolean":
				$data = (boolean) $data;
				return $data;
				break;
			case "DateTime":
				$data = trim($data);
				if(preg_match("/^\d{4}-\d{2}-\d{2}T[0-2][0-3]:[0-5][0-9]:[0-5][0-9]Z$/", $data)) return $data;
				break;
            		case "TimeStamp":
				$data = trim($data);
				if(preg_match("/^\d{4}-\d{2}-\d{2}T[0-2][0-3]:[0-5][0-9]:[0-5][0-9]Z$/", $data)) return $data;
				break;
            		case "Y-m-d":
				$data = trim($data);
				if(preg_match("/^\d{4}-\d{2}-\d{2}$/", $data)) return $data;
				break;
		}
		$this->validationErrors[] = $name." (".$type.") ".$data." not allowed/malformatted/out-of-range";
		return NULL;
	}

	public function validate() {
		return $this->conformComplexSOAPType("SOAPRequest");
	}

	public function getValidationErrors() {
		return $this->validationErrors;
	}
	//--------------------------------------

	//--------------------------------------
	//XML Generating methods

	public function createXMLString($rootName) {
		$this->xmlDoc = new DomDocument();
		$this->xmlDoc->preserveWhiteSpace = false;
		foreach($this->cdata as $name => $value) {
			$root = $value->getXML($name, $this->xmlDoc);
			$this->xmlDoc->appendChild($root);
			$cdata = $this->xmlDoc->saveXML($root);
			return $cdata;
			/*$newDoc = new DomDocument();
			$newDoc->preserveWhiteSpace = false;
			$xmlString = $this->xmlDoc->createCDATASection($cdata);
			$xmlString = $newDoc->importNode($xmlString, true);
			$newDoc->appendChild($xmlString);
			return $newDoc->saveXML($xmlString);*/
		}
	}

	public function getXML($childName, $domDoc) {
		$this->xmlDoc = $domDoc;
		$child = $this->xmlDoc->createElement($childName);

		foreach($this->pdata as $name => $value) {
			$attr = $this->xmlDoc->createAttribute($name);
			$text = $this->xmlDoc->createTextNode($value);
			$attr->appendChild($text);
			$child->appendChild($attr);
		}

		if($this->vdata) {
			$child->appendChild($this->xmlDoc->createTextNode($this->vdata));
		}
		elseif(count($this->adata) > 0) {
			reset($this->elements);
			$name = key($this->elements);
			foreach($this->adata as $index => $value) {
				if(gettype($value) == "object") {
					if(method_exists($value, "getXML")) {
						$child->appendChild($value->getXML($name, $this->xmlDoc));
					}
					else {
						//this should never occur: no way to produce xml from standard object
					}
				}
				else {
					$elem = $this->xmlDoc->createElement($name, $value);
					$child->appendChild($elem);
				}
			}
		}
		else {

			foreach($this->cdata as $name => $value) {
				if(gettype($value) == "object") {
					if(method_exists($value, "getXML")) {
						$child->appendChild($value->getXML($name, $this->xmlDoc));
					}
					else {
						//this should not occur
					}
				}
				else {
					$elem = $this->xmlDoc->createElement($name, $value);
					$child->appendChild($elem);
				}
			}
		}
		return $child;
	}
}

class UnserializableRecord {
	private $cdata = array();

	public function __get($name) {
		if(isset($this->cdata[$name])) return $this->cdata[$name];
		else return NULL;
	}

	public function __set($name, $value) {
		$this->cdata[$name] = $value;
	}

	public function getSetChildren() {
		return $this->cdata;
	}
}
?>
