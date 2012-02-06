<?php

namespace Proudlygeek\Yamlr;

use Symfony\Component\ClassLoader\UniversalClassLoader;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Collections\ArrayCollection;


class Yamlr
{
    private $parser;
    private $manager;

    public function __construct(\Doctrine\Common\Persistence\ObjectManager $manager, $parser) 
    {
        $this->manager = $manager;
        $this->parser = $parser;
    }
   
    public function loadFixtures($path)
    {
        $loader = new UniversalClassLoader();
        $loader->register();

        // Getter
        $fixtures = $this->parser->parse(file_get_contents($path));

        // Import class namespace
        $classMux = $fixtures['Namespaces'];
        unset($fixtures['Namespaces']);
 
        $recordslist = array();
        // Makes an array of entity fixtures
        foreach ( $fixtures as $class => $records )
        {
 
            foreach ( $records as $identificator => $record )
            {
                
                $preparobj = $classMux[$class];
                
                $recordslist[ $identificator ][ 'class' ] = $class;
                $recordslist[ $identificator ][ 'entity' ] = new $preparobj();
                
                $recordslist[ $identificator ][ 'data' ] = $record;
            }
        }
        
        //var_dump($recordslist);exit;
 
        foreach ( $recordslist as $record )
        {
            foreach ( $record[ 'data' ] as $field => $value )
            {
                
                if (preg_match("/_join$/", $field))
                {
                    $field = preg_replace("/_join$/", "", $field);
                    
                    //var_dump($value);
                    if (is_array($value)) {
                        // Has multiple values (one-to-many or many-to-many)
                        $tmp = array();
                        foreach($value as $item) {
                            $tmp[]= $recordslist[$item]['entity'];
                        }
                        $value = new ArrayCollection($tmp);
                        
                    } else {
                        $value =  $recordslist[ $value ][ 'entity' ];
                   }
                }
                
                // My Custom override for datetime objects
                if (preg_match("/_datetime$/", $field)) {
                    $field = preg_replace("/_datetime$/", "", $field);
                    
                    call_user_func( array(
                        $record[ 'entity' ], 'set' . ucfirst( $field )
                            ), new \DateTime($value)
                    );
                } else {
                    call_user_func( array(
                        $record[ 'entity' ], 'set' . ucfirst( $field )
                            ), $value
                    );  
                } 
            }
            
            $this->manager->persist( $record[ 'entity' ] );
        }
        //var_dump($recordslist);
        $this->manager->flush();
    }
}