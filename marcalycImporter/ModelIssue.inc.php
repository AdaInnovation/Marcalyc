<?php


//datos del Numero
class Numero{
    var $Title;
    var $Volume;
    var $Number;
    var $Year;
    var $Status;
    var $JournalID;

    public function __construct($journalId,$title,$volume,$number,$year)
    {
        $this->Title = $title;
        $this->Volume = $volume;
        $this->Number = $number;
        $this->Year = $year;
        $this->JournalID = $journalId;
    }

    public function getTitle(){
        return $this->Title ;
    }

    public function getVolume(){
        return $this->Volume;
    }

    public function getNumber(){
        return $this->Number;
    }

    public function getYear(){
        return $this->Year;
    }

    public function getJournalId(){
        return $this->JournalID;
    }

    public function setStatus($status){
        $this->Status = $status;
    }

    

}

//datos del articulo

class Articulo{
    var $id_articulo;
    var $categoria_articulo;
    var $categoria_id;
    var $Numero;
    var $status;
    var $journal_id_redalyc;
    var $article_id_redalyc;

    
    public function __construct($id,$categoria){
        $this->id_articulo = $id;
        $this->categoria_articulo = $categoria;
    }

    public function setNumero($numero){
        $this->Numero = $numero;
    }

    public function setTitle($titulo,$idioma){
        $this->setData('title', $titulo, $idioma);
    }

    public function setAbstract($abstract,$locale){
        $this->setData('abstract', $abstract, $locale);
    }

    public function setLanguage($language){
        $this->setData('language', $language);
    }

    public function setAutor($autor,$index){
        $this->setData('Autores', $autor, $index);
    }

    function setCategoria($categoria){
        $this->categoria_articulo=$categoria;
    }

    function setIdJournalRedalyc($idJournalRedalyc){
        $this->journal_id_redalyc=$idJournalRedalyc;
    }

    function setIdArticleRedalyc($idArticleRedalyc){
        $this->article_id_redalyc=$idArticleRedalyc;
    }
    
    function setData($key, $value, $locale = null) {
		if (is_null($locale)) {
				$this->$key = $value;
		} else {
			if (is_null($value)) {

			} else {
				$this->$key[$locale] = $value;
			}
		}
    }

    function getNumero(){
        return $this->Numero;
    }
}

// Datos de los autores del articulo
class Autor{
    var $nombre;
    var $apellidos;
    var $email;
    var $institucion;
    var $pais;
    var $status;

    function setNombre($nombre){
        $this->nombre = $nombre;
    }

    function setApellidos($apellidos){
        $this->apellidos = $apellidos;
    }

    function setEmail($email){
        $this->email = $email;
    }

    function setInstitucion($institucion){
        $this->institucion = $institucion;
    }

    function setPais($pais){
        $this->pais = $pais;
    }

    function setData($key, $value, $locale = null) {
		if (is_null($locale)) {
				$this->$key = $value;
		} else {
			if (is_null($value)) {
                
			} else {
				$this->$key[$locale] = $value;
			}
		}
    }
}

?>