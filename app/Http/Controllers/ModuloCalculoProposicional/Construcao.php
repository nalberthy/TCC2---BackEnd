<?php

namespace App\Http\Controllers\ModuloCalculoProposicional;

use Illuminate\Http\Request;
use App\Http\Controllers\ModuloCalculoProposicional\Formula\Argumento;
use App\Http\Controllers\ModuloCalculoProposicional\Formula\Regras;
use App\Http\Controllers\Controller;

class Construcao extends Controller
{
    function __construct() {
        $this->arg = new Argumento;
        $this->reg = new Regras;
    }

    // Gera etapa de apresentação inicial
    public function gerar($derivacao,$premissas){
        $derivacoes=[];
        $indice=1;
        foreach ($derivacao as $i) {
            $i->setIndice($indice);
            if (in_array($i->getPremissa(), $premissas, true)){
                $i->setIdentificacao('p');
            }
            $derivacoes[]= ['indice'=>$indice,'str'=>$this->arg->stringArg($i->getPremissa()->getValor_obj()),'ident'=>$i->getIdentificacao()];
            $indice+=1;
        }
        
        return $derivacoes;
    }   

    public function aplicarRegra($derivacoes,$linha1,$linha2,$linha3,$regra,$xml_entrada){
        $linha1=$linha1-1;
        
        if ($linha2 != null){
            $linha2=$linha2-1;
        }
        if ($linha3 != null){
            $linha3=$linha3-1;
        }
       
        
// -----------------------------------------VERIFICAÇÃO DE INDICE POR TAMANHO DA LISTA ---------------------------
        if($linha1>=count($derivacoes)){
            
            return False;
        }
 
        if($linha2 >= count($derivacoes)){
            
            return False;
        }
        if($linha3 >= count($derivacoes)){
            
            return False;
        }
// --------------------------------------------------------------------------------------------------------------


        if ($regra == 'Modus_Ponens'){
            if ($linha1 == -1){return False;}
            if ($linha2 == -1){return False;}
            if ($derivacoes[$linha1]->getPremissa()->getValor_obj()->getTipo()=="CONDICIONAL"){
                if($derivacoes[$linha1]->getPremissa()->getValor_obj()->getEsquerdaValor()==$derivacoes[$linha2]->getPremissa()->getValor_obj()->getValor()){
                    $aplicado= $this->reg->ModusPonens($derivacoes,$derivacoes[$linha1],$derivacoes[$linha2]);
                    $aplicado->setIdentificacao(($linha1+1).','.($linha2+1).' mp');
                    array_push($derivacoes,$aplicado);
                    return $derivacoes;
                   
                }
            }
        
            elseif ($derivacoes[$linha2]->getPremissa()->getValor_obj()->getTipo()=="CONDICIONAL"){
                if($derivacoes[$linha2]->getPremissa()->getValor_obj()->getEsquerdaValor()==$derivacoes[$linha1]->getPremissa()->getValor_obj()->getValor()){
                    $aplicado=$this->reg->ModusPonens($derivacoes,$derivacoes[$linha1],$derivacoes[$linha2]); 
                    $aplicado->setIdentificacao(($linha2+1).','.($linha1+1).' mp');
                    array_push($derivacoes,$aplicado);
                    return $derivacoes;
                }

            }
            else{
                return False;
            }

        }

        elseif($regra=='Introducao_Disjuncao'){
            if($xml_entrada == null){return FALSE;};

            try{$xml= simplexml_load_string($xml_entrada);}
            catch(\Exception $e){return response()->json(['success' => false, 'msg'=>'XML INVALIDO!', 'data'=>''],500);}
            $obj_xml = $this->arg->arrayPremissas($xml);
           
            $aplicado=$this->reg->IntroducaoDisjuncao($derivacoes,$derivacoes[$linha1],$obj_xml[0]);

            $aplicado->setIdentificacao(($linha1+1).' vI');


            array_push($derivacoes,$aplicado);
            return $derivacoes;
        }
        elseif($regra=='Eliminacao_Disjuncao'){
            $aplicado=$this->reg->EliminacaoDisjuncao($derivacoes,$derivacoes[$linha1],$derivacoes[$linha2],$derivacoes[$linha3]);
            $aplicado->setIdentificacao(($linha1+1).','.($linha2+1).','.($linha3+1).' vE');
         
            array_push($derivacoes,$aplicado);
            return $derivacoes;
        }
        elseif($regra=='Introducao_Conjuncao'){
            $aplicado=$this->reg->IntroducaoConjuncao($derivacoes,$derivacoes[$linha1],$derivacoes[$linha2]);
            $aplicado->setIdentificacao(($linha1+1).','.($linha2+1).' ^I');

            array_push($derivacoes,$aplicado);
            return $derivacoes;
        }

        elseif($regra=='Eliminacao_Conjuncao'){
            $derivacoes= $this->reg->EliminacaoConjuncao($derivacoes,$derivacoes[$linha1],$linha1);
            return $derivacoes;
        }
        elseif($regra=='Eliminacao_Negacao'){
            $derivacoes=$this->reg->ElimicacaoNegacao($derivacoes,$derivacoes[$linha1],$linha1);
            return $derivacoes;
        }
        elseif($regra=='Introducao_Bicondicional'){
            if($derivacoes[$linha1]->getPremissa()->getValor_obj()->getTipo()=='CONDICIONAL' and $derivacoes[$linha2]->getPremissa()->getValor_obj()->getTipo()=='CONDICIONAL'){
                $aplicado=$this->reg->IntroducaoBicondicional($derivacoes,$derivacoes[$linha1],$derivacoes[$linha2]);

                $aplicado->setIdentificacao(($linha1+1).','.($linha2+1).' ↔I');
                array_push($derivacoes,$aplicado);
                return $derivacoes;
            }
            return FALSE;
            
        }
        elseif($regra=='Eliminacao_Bicondicional'){
            $derivacoes= $this->reg->EliminacaoBicondicional($derivacoes,$derivacoes[$linha1],$linha1);
            return $derivacoes;
        }
        elseif($regra=='PC'){
            if($xml_entrada == null){return FALSE;};

            try{$xml= simplexml_load_string($xml_entrada);}
            catch(\Exception $e){return response()->json(['success' => false, 'msg'=>'XML INVALIDO!', 'data'=>''],500);}
            $obj_xml = $this->arg->arrayPremissas($xml);
           
            
        }
        elseif($regra=='Raa'){
            // Disponibilizado em breve
       }
    }

    #reconstrói objeto a partir de array com regras aplicadas anteriormente 
    public function gerarPasso($derivacao,$passo){
        if($passo!=[]){
            foreach ($passo as $i) {
                $derivacao= $this->aplicarRegra($derivacao,$i['entrada1'],$i['entrada2'],$i['entrada3'],$i['regra'],$i['xml_entrada']);
            }
            return $derivacao;
        }
        else{
            return $derivacao;
        }
        
    }  
    

    public function verificaConclusao($conclusao,$derivacao){

        foreach ($derivacao as $i){
            if($this->arg->stringArg($conclusao[0]->getValor_obj())==$this->arg->stringArg($i->getPremissa()->getValor_obj())){
                return TRUE;
            }
        }
        
    }
    
}
