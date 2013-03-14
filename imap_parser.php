<?php

//script will fetch an email identified by $msgid, and parse its parts into an
//array $partsarray
//structure of array:
//$partsarray[<name of part>][<attachment/text>]
//if attachment- subarray is [filename][binary data]
//if text- subarray is [type of text(HTML/PLAIN)][text string]

//i.e.
//$partsarray[1][attachment][filename]=filename of attachment in part 3.1
//$partsarray[1][attachment][binary]=binary data of attachment in part 3.1
//$partsarray[2][text][type]=type of text in part 2
//$partsarray[2][text][string]=decoded text string in part 2
//$partsarray[not multipart][text][string]=decoded text string in message that isn't multipart

function parsepart($p, $i){
    global $mbox, $msgid, $partsarray;
    //where to write file attachments to:
	$filestore = '[full/path/to/attachment/store/(chmod775)]';
    
    //fetch part
    $part=imap_fetchbody($mbox, $msgid, $i);
    //if type is not text
    if ($p->type != 0){
        //DECODE PART       
        //decode if base64
        if ($p->encoding == 3) {
        	$part=base64_decode($part);
        }
        //decode if quoted printable
        if ($p->encoding == 4) {
        	$part=quoted_printable_decode($part);
        }
        //no need to decode binary or 8bit!
       
        //get filename of attachment if present
        $filename = '';
        // if there are any dparameters present in this part
        if (count($p->dparameters) > 0){
            foreach ($p->dparameters as $dparam){
                if ((strtoupper($dparam->attribute) == 'NAME') ||(strtoupper($dparam->attribute) == 'FILENAME')) {
                	$filename = $dparam->value;
                }
            }
        }
        //if no filename found
        if ($filename == ''){
            // if there are any parameters present in this part
            if (count($p->parameters) > 0){
                foreach ($p->parameters as $param){
                    if ((strtoupper($param->attribute) == 'NAME') ||(strtoupper($param->attribute) == 'FILENAME')) {
                    	$filename = $param->value;
                    }
                }
            }
        }
        //write to disk and set partsarray variable
        if ($filename != ''){
			$tempfilename = imap_mime_header_decode($filename);
			for ($i = 0; $i < count($tempfilename); $i++) {
    			$filename =  $tempfilename[$i]->text;
			}
            $partsarray[$i][attachment] = array('filename'=>$filename, 'binary'=>$part);
            $fp = fopen($filestore.$filename, "w+");
            fwrite($fp, $part);
            fclose($fp);
        }
    //end if type!=0       
    }
   
    //if part is text
    else if($p->type == 0) {
        //decode text
        //if QUOTED-PRINTABLE
        if ($p->encoding == 4) {
        	$part = quoted_printable_decode($part);
        }
        //if base 64
        if ($p->encoding == 3) {
        	$part = base64_decode($part);
       	}
        
        //OPTIONAL PROCESSING e.g. nl2br for plain text
        //if plain text
        if (strtoupper($p->subtype) == 'PLAIN')1;
        //if HTML
        else if (strtoupper($p->subtype) == 'HTML')1;
        $partsarray[$i][text] = array('type'=>$p->subtype, 'string'=>$part);
    }
   
    //if subparts... recurse into function and parse them too!
    if (count($p->parts) > 0){
        foreach ($p->parts as $pno=>$parr){
            parsepart($parr, ($i . '.' . ($pno+1)));           
        }
    }
	return;
}

//SCRIPT STARTS HERE... EVERYTHING ABOVE IS A FUNCTION

//Open the connection to IMAP server
$mbox = imap_open("{server.host.name}", "email@address.com", "password_for_email_account")
      or die("can't connect: " . imap_last_error());
			
$status = @imap_status($mbox, "{server.host.name}INBOX", SA_ALL);

$msgid = $status->messages;

if ($msgid == 0) {
	die();
}

do {

	//fetch structure of message
	$s = imap_fetchstructure($mbox, $msgid);

	//see if there are any parts
	if (count($s->parts) > 0){
		foreach ($s->parts as $partno=>$partarr) {
	    	//parse parts of email
	    	parsepart($partarr, $partno+1);
	    }
	}

	//for not multipart messages
	else {
	    //get body of message
	    $text=imap_body($mbox, $msgid);
	    //decode if quoted-printable
		if ($s->encoding == 3) {
			$text = base64_decode($text);
		}
	    if ($s->encoding == 4) {
	    	$text = quoted_printable_decode($text);
	    }
	    //OPTIONAL PROCESSING
	    if (strtoupper($s->subtype) == 'PLAIN') {
	    	$text=$text;
	    }
	    if (strtoupper($s->subtype) == 'HTML') {
	    	$text=$text;
	   	}
	    $partsarray['not multipart'][text] = array('type'=>$s->subtype, 'string'=>$text);
	}

	$header = imap_fetchheader($mbox, $msgid);
	$obj = imap_rfc822_parse_headers($header);

	$filename = basename($partsarray[1]['attachment']['filename']);
	$from = $obj->reply_toaddress;
	$notes = $partsarray['not multipart']['text']['string'];
	if ($notes == "") {
		$notes = $partsarray[1]['text']['string'];
	}

	imap_delete($mbox, $msgid);
	imap_expunge($mbox);
	$msgid--;
} while ($msgid > 0);
imap_close($mbox);
?>
