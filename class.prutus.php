<?php
namespace Orpheus;

class Prutus {
    protected $permutation = false;
    protected $wordList = array();

    protected $cURLHandler = null, $buffer = 10;

    protected $originalURL = '', $url = array();
    protected $postFields = array();


    protected $fingerprints = array();
    protected $hash = false, $hashType = null, $pattern = '%word%';

    /**
     *
     * Constructor.
     *
     * @param string $url        The target URL.
     * @param string $postFields The post fields for the target URL.
     * @param string $wordList   The word list or directory of wordlists.
     *
     */
    public function __construct($url = null, array $postFields = null, $wordList = null) {
        if($wordList !== null) {
            if(is_dir($wordList)) {
                $this->loadDictionaries($wordList);
            } else if(is_file($wordList)) {
                $this->setDictionary($wordList);
            }
        }
        
        if($url !== null) {
            if($postFields !== null) {
                $this->setTargetURL($url, $postFields);   
            } else {
                $this->setTargetURL($url);
            }
        }

        $this->loadFingerPrints(__DIR__ . '/common/hash_fingerprints.json');
    }
    
    /**
     *
     * Start Prutus.
     *
     * @param closure $parse  The lamdba function which will parse data for valid output.
     * @param int     $rounds The number of rounds to do. 0 = Go until completion 
     *
     * @return mixed
     *
     */
    public function start(\closure $parse = null, $rounds = 0) {
        if($this->hash === false) {
            if($parse === null) {
                throw new \Exception(__METHOD__ . ': $parse was null');
            }
            $this->cURLHandler = curl_multi_init();
            $ch = array();

            if(!$this->permutation) {
                foreach($this->wordList as $word) {
                    $ch[$word] = curl_init($this->originalURL);
                    $postFields = array_map(function($value) use($word) {
                        return preg_replace('/\%word\%/', $word, $value);
                    }, $this->postFields);
                    curl_setopt_array($ch[$word], array(CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => $postFields));
                    curl_multi_add_handle($this->cURLHandler, $ch[$word]);
                }

                $running = null;
                do {
                    curl_multi_exec($this->cURLHandler, $running);
                } while($running > 0);

                foreach($ch as $password => $handler) {
                    $ch[$password] = $parse(curl_multi_getcontent($handler), $password);
                }
            } else {
                $h = 0;
                $i = 0;
                $done = false;
                while($done == false) {
                    for($j = 0; $j < $this->buffer; $j++) {
                        $word = base_convert($h, 10, 36);
                        $ch[$word] = curl_init($this->originalURL);
                        $postFields = array_map(function($value) use($word) {
                            return preg_replace('/\%word\%/', $word, $value);
                        }, $this->postFields);
                        curl_setopt_array($ch[$word], array(CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => $postFields));
                        curl_multi_add_handle($this->cURLHandler, $ch[$word]);
                    }

                    $running = null;
                    do {
                        curl_multi_exec($this->cURLHandler, $running);
                    } while($running > 0);

                    foreach($ch as $password => $handler) {
                        $ch[$password] = $parse(curl_multi_getcontent($handler), $password);
                        if($ch[$password]) {
                            return $password;
                        }
                    }

                    $ch = array();

                    if($rounds > 0) {
                        $i++;
                        if($i >= $rounds) {
                            $done = true;
                        }
                    }
                    $h++;
                }
            }
            return $ch;
        } else {
            if(!$this->permutation) {
                foreach($this->wordList as $word) {
                    $pattern = preg_replace('/\%word\%/', $word, $this->pattern);
                    $hash = hash($this->hashType, $pattern);
                    if($hash == $this->hash) {
                        return $word;
                    }
                }
            } else {
                $h = 0;
                $i = 0;
                $done = false;
                $timeBump = time();

                while($done == false) {
                    if($i > $rounds) {
                        $done = true;
                    }
                    if(time() - $timeBump >= 1) {
                        echo '.';
                        $timeBump = time();
                    }
                    $word = base_convert($h, 10, 36);
                    $pattern = preg_replace('/\%word\%/', $word, $this->pattern);
                    $hash = hash($this->hashType, $pattern);
                    if($hash == $this->hash) {
                        return $word;
                    }
                    $h++;

                    if($rounds > 0) {
                        $i++;
                    }
                }
                echo PHP_EOL;
                return false;
            }
        }
    }

    /**
     *
     * Load a set of finger prints (hash regexps).
     *
     * @param string $filename The fingerprint file.
     *
     * @return bool
     *
     */
    public function loadFingerPrints($filename) {
        if(!is_file($filename)) {
            throw new \Exception(__METHOD__ . ': ' . $filename . ' does not exist.');
        }

        if($this->fingerprints = json_decode(file_get_contents($filename))) {
            return true;
        }

        throw new \Exception(__METHOD__ . ': ' . $filename . ' contains malformed JSON.');
    }

    /**
     *
     * Set the hash to bruteforce.
     *
     * @param string $hash The hash to bruteforce.
     *
     * @return string
     *
     */
    public function setHash($hash, $type = null, $pattern = '%word%') {
        $this->hash = $hash;
        $this->pattern = $pattern;
        if($type !== null) {
            if(!in_array($type, hash_algos())) {
                throw new \Exception(__METHOD__ . ': Unknown hash method "' . $type . '"');
            }
            $this->hashType = $type;
        }


        foreach($this->fingerprints as $hashType => $fingerprint) {
            if(preg_match('/' . $fingerprint . '/', $hash)) {
                return $hashType;
            }
        }
    }

    /**
     * 
     * Set the target URL, and POST fields if needed.
     *  
     * @param string $url        The target URL.
     * @param array  $postFields The POST fields. Optional.
     * 
     * @return bool
     * 
     */
    public function setTargetURL($url, array $postFields = null) {
        if($postFields !== null) {
            $this->postFields = $postFields;
        }
        $parsedURL = parse_url($url);
        if($parsedURL === false) {
            throw new \Exception(__METHOD__ . ': Seriously malformed URL: ' . htmlentities($url) . '.');
        }
        $this->url = $parsedURL;
        $this->originalURL = $url;
    }

    /**
     *
     * Load a single word list.
     *
     * @param  string $file The word list.
     * @return bool
     */
    public function setDictionary($file) {
        if(!is_file($file)) {
            throw new Exception(__METHOD__ . ': ' . $file . ' is not a file.');
        }

        $this->wordList = file($file);
        return true;
    }

    /**
     *
     * Load all word lists in a directory.
     *
     * @param  string $dir The directory with word lists in it.
     * @return bool
     */
    public function loadDictionaries($dir = 'dictionaries') {
        if(!is_dir($dir)) {
            throw new Exception(__METHOD__ . ': ' . $dir . ' is not a directory.');
        }

        $wordLists = glob($dir . '/*.list');

        if(count($wordLists) == 0) {
            throw new Exception(__METHOD__ . ': No word lists found in ' . $dir);
        }

        foreach($wordLists as $wordList) {
            $this->wordList = array_merge($this->wordList, file($wordList));
        }

        return true;
    }

    /**
     *
     * Set the permutation status.
     *
     * @param bool $permutation
     *
     */
    public function setPermutation($permutation) {
        if(!is_bool($permutation)) {
            throw new Exception(__METHOD__ . ': $permutation is not a bool.');
        }

        $this->permutation = $permutation;
    }

    /**
     *
     * Set the buffer count.
     *
     * @param int $count The buffer count
     *
     */
    public function setBuffer($count) {
        $count = (int) $count;
        $this->buffer = $count;
    }
}
