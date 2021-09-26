<?php
/**
 * ask akismet.com if this comment is likely spam
 *
 * @author mnt@codeninja.de
 * @package kata
 */

class AkismetUtility {

    private $key;
    private $blog;


    function __construct($key='',$blog='') {
        $this->key  = $key;
        $this->blog = $blog;
    }

    function isSpam($ip='',$author='',$email='',$url='',$content='') {
        $params = array(
            'blog' => $this->blog,
            'user_ip' => $ip,
            'comment_author' => $author,
            'comment_author_email' => $email,
            'comment_author_url' => $url,
            'comment_content' => $content
        );

        return $this->open_url('check',$params) == 'true';
    }

    function addHam($ip='',$author='',$email='',$url='',$content='') {
        $params = array(
            'blog' => $this->blog,
            'user_ip' => $ip,
            'comment_author' => $author,
            'comment_author_email' => $email,
            'comment_author_url' => $url,
            'comment_content' => $content
        );

        return $this->open_url('ham',$params) == 'true';
    }

    function addSpam($ip='',$author='',$email='',$url='',$content='') {
        $params = array(
            'blog' => $this->blog,
            'user_ip' => $ip,
            'comment_author' => $author,
            'comment_author_email' => $email,
            'comment_author_url' => $url,
            'comment_content' => $content
        );

        return $this->open_url('spam',$params) == 'true';
    }

    function verify() {
        $params = array(
            'key' => $this->key,
            'blog' => $this->blog
        );
        return $this->open_url('verify', $params) == 'valid';

    }

    var $url = array(
        'check' => 'http://${api}.rest.akismet.com/1.1/comment-check',
        'spam'  => 'http://${api}.rest.akismet.com/1.1/submit-spam',
        'ham'   => 'http://${api}.rest.akismet.com/1.1/submit-ham',
        'verify'=> 'http://rest.akismet.com/1.1/verify-key'
    );

    function get_url($type) {
        $api = & $this->key;
        return eval('return "'.$this->url[$type].'";');
    }

    function open_url($type,$params=array()) {
    /*    $http = new http_class;
        $http->request_method='POST';
        $http->user_agent = "cesar-rodas/1.0 | Akismet-Class/".CLASS_VERSION;
        $http->follow_redirect=1;
        $http->redirection_limit=5;
        $http->exclude_address="";
        $http->protocol_version="1.1";
        $http->GetRequestArguments( $this->get_url($type) ,$arguments);

        $arguments['PostValues'] = $params;


        $this->err = $http->Open($arguments);
        if ($this->err != "") return false;

        $this->err = $http->SendRequest($arguments);
        if ($this->err != "") return false;

        $this->err = $http->ReadReplyHeaders($gHeaders);
        if ($this->err != "") return false;

        if ($http->response_status != 200) {
            $this->err = "Pages status: ".$http->response_status;
            $http->Close();
            return false;
        }
        $response = '';
        for(;;)
        {
            $this->error=$http->ReadReplyBody($body,1000);
            if($this->error!=""
            || strlen($body)==0)
                break;
            $response .= $body;
        }
        $http->close();
        return $response;*/
    }

}
?>