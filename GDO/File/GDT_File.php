<?php
namespace GDO\File;
use GDO\Core\GDT_Template;
use GDO\User\GDO_Session;
use GDO\Util\Arrays;
use GDO\Core\Logger;
use GDO\Core\GDO;
use GDO\DB\GDT_Object;
use GDO\UI\WithHREF;
/**
 * File input and upload backend for flow.js
 * @author gizmore
 * @since 4.0
 * @version 5.0
 */
class GDT_File extends GDT_Object
{
    use WithHREF;
    
    public function defaultLabel() { return $this->label('file'); }
    
    public function __construct()
    {
        $this->table(GDO_File::table());
    }
    
    public $mimes = [];
    public function mime($mime)
    {
        $this->mimes[] = $mime;
        return $this;
    }
    
    public function imageFile()
    {
        $this->mime('image/jpeg');
        $this->mime('image/gif');
        $this->mime('image/png');
        return $this->preview();
    }
    
    public $minsize = 1024;
    public function minsize($minsize)
    {
        $this->minsize = $minsize;
        return $this;
    }
    public $maxsize = 4096 * 1024;
    public function maxsize($maxsize)
    {
        $this->maxsize = $maxsize;
        return $this;
    }
    
    public $preview = false;
    public function preview($preview=true)
    {
        $this->preview = $preview;
        return $this;
    }
    public $previewHREF;
    public function previewHREF($previewHREF=null) { $this->previewHREF = $previewHREF; return $this->preview($previewHREF!==null); }
    public function displayPreviewHref(GDO_File $file) { return $this->previewHREF . $file->getID(); }
    
    public $multiple = false;
    public $minfiles = 0;
    public $maxfiles = 1;
    public function minfiles($minfiles)
    {
        $this->minfiles = $minfiles;
        return $minfiles  > 0 ? $this->notNull() : $this;
    }
    public function maxfiles($maxfiles)
    {
        $this->maxfiles = $maxfiles;
        $this->multiple = $maxfiles > 1;
        return $this;
    }
    
    public $action;
    public function action($action)
    {
        $this->action = $action.'&ajax=1&fmt=json&flowField='.$this->name;
        return $this;
    }
    public function getAction()
    {
        if (!$this->action)
        {
            $this->action($_SERVER['REQUEST_URI']);
        }
        return $this->action;
    }
    
    ##############
    ### Render ###
    ##############
    public function renderForm()
    {
        return GDT_Template::php('File', 'form/file.php', ['field'=>$this]);
    }
    
    public function renderCell()
    {
        return GDT_Template::php('File', 'cell/file.php', ['gdo'=>$this->getValue()]);
    }
    
    public function toJSON()
    {
        return array(
//             'label' => $this->label,
            'mimes' => $this->mimes,
            'minsize' => $this->minsize,
            'maxsize' => $this->maxsize,
            'minfiles' => $this->minfiles,
            'maxfiles' => $this->maxfiles,
            'preview' => $this->preview,
            'previewHREF' => $this->previewHREF,
            'selectedFiles' => $this->initJSONFiles(),
        );
    }
    
    public function initJSONFiles()
    {
        $json = [];
        $files = Arrays::arrayed($this->getValue());
        foreach ($files as $file)
        {
            $file instanceof GDO_File;
            $file->tempHref($this->href);
            $json[] = $file->toJSON();
        }
        return $json;
    }
    
    #############
    ### Value ###
    #############
    private $files = [];
    public function toVar($value)
    {
        if ($value !== null)
        {
            return $this->multiple ? $this->toMultiVar($value) : $value->getID();
        }
    }
    
    public function toValue($var)
    {
        if ($var !== null)
        {
            return $this->multiple ? $this->toMultiValue($var) : GDO_File::getById($var);
        }
    }
    
    public function getVar()
    {
        return $this->toVar($this->getValue());
    }
    
    public function getInitialFiles()
    {
        return $this->multiple ? $this->getMultiInitialFiles() : Arrays::arrayed($this->getInitialFile());
    }
    
    public function getInitialFile()
    {
        if ($this->var !== null)
        {
            return GDO_File::getById($this->var);
        }
    }
    
    public function setGDOData(GDO $gdo=null)
    {
        if (!$this->multiple)
        {
            return parent::setGDOData($gdo);
        }
    }
    
//     public function getGDOData()
//     {
//         return parent::getGDOData();
//     }
    
    /**
     * @return GDO_File
     */
    public function getValidationValue()
    {
        $files = array_merge($this->getInitialFiles(), $this->getFiles($this->name));
        return $this->multiple ? $files : array_pop($files);
    }
    
    public function getValue()
    {
        $files = array_merge($this->getInitialFiles(), Arrays::arrayed($this->files));
        return $this->multiple ? $files : array_pop($files);
    }
    
    ################
    ### Validate ###
    ################
    public function validate($value)
    {
        $valid = true;
        $files = Arrays::arrayed($value);
        if ( ($this->notNull) && (empty($files)) )
        {
            $valid = $this->error('err_upload_min_files', [1]);
        }
        elseif (count($files) < $this->minfiles)
        {
            $valid = $this->error('err_upload_min_files', [max(1, $this->minfiles)]);
        }
        elseif (count($files) > $this->maxfiles)
        {
            $valid = $this->error('err_upload_max_files', [$this->maxfiles]);
        }
        else
        {
            foreach ($files as $file)
            {
                $file instanceof GDO_File;
                if (!$file->isPersisted())
                {
                    $file->copy();
                }
            }
            $this->files = $files;
        }
        if (!$valid)
        {
            $this->cleanup();
        }
        return $valid;
    }
    
    ###################
    ### Flow upload ###
    ###################
    private function getTempDir($key='')
    {
        return GWF_PATH.'temp/flow/'.GDO_Session::instance()->getID().'/'.$key;
    }
    
    private function getChunkDir($key)
    {
        $chunkFilename = str_replace('/', '', $_REQUEST['flowFilename']);
        return $this->getTempDir($key).'/'.$chunkFilename;
    }
    
    private function denyFlowFile($key, $file, $reason)
    {
        return @file_put_contents($this->getChunkDir($key).'/denied', $reason);
    }
    
    private function deniedFlowFile($key, $file)
    {
        $file = $this->getChunkDir($key).'/denied';
        return FileUtil::isFile($file) ? file_get_contents($file) : false;
    }
    
    private function getFile($key)
    {
        if ($files = $this->getFiles($key))
        {
            return array_shift($files);
        }
    }
    
    private function getFiles($key)
    {
        $files = array();
        $path = $this->getTempDir($key);
        if ($dir = @dir($path))
        {
            while ($entry = $dir->read())
            {
                if (($entry !== '.') && ($entry !== '..'))
                {
                    if ($file = $this->getFileFromDir($path.'/'.$entry))
                    {
                        $files[$file->getName()] = $file;
                    }
                }
            }
        }
        return $files;
    }
    
    /**
     * @param string $dir
     * @return GDO_File
     */
    private function getFileFromDir($dir)
    {
        if (FileUtil::isFile($dir.'/0'))
        {
            return GDO_File::fromForm(array(
                'name' => @file_get_contents($dir.'/name'),
                'mime' => @file_get_contents($dir.'/mime'),
                'size' => filesize($dir.'/0'),
                'dir' => $dir,
                'path' => $dir.'/0',
            ));
        }
    }
    
    public function onValidated()
    {
        $this->cleanup();
    }
    
    public function cleanup()
    {
        $this->files = null;
        FileUtil::removeDir($this->getTempDir());
    }
    
    ############
    ### Flow ###
    ############
    public function flowUpload()
    {
        foreach ($_FILES as $key => $file)
        {
            $this->onFlowUploadFile($key, $file);
        }
        die();
    }
    
    private function onFlowError($error)
    {
        header("HTTP/1.0 413 $error");
        Logger::logError("FLOW: $error");
        echo $error;
        return false;
    }
    
    private function onFlowUploadFile($key, $file)
    {
        $chunkDir = $this->getChunkDir($key);
        if (!FileUtil::createDir($chunkDir))
        {
            return $this->onFlowError('Create temp dir');
        }
        
        if (false !== ($error = $this->deniedFlowFile($key, $file)))
        {
            return $this->onFlowError("Denied: $error");
        }
        
        if (!$this->onFlowCopyChunk($key, $file))
        {
            return $this->onFlowError("Copy chunk failed.");
        }
        
        if ($_REQUEST['flowChunkNumber'] === $_REQUEST['flowTotalChunks'])
        {
            if ($error = $this->onFlowFinishFile($key, $file))
            {
                return $this->onFlowError($error);
            }
        }
        
        # Announce result
        $result = json_encode(array(
            'success' => true,
        ));
        echo $result;
        return true;
    }
    
    private function onFlowCopyChunk($key, $file)
    {
        if (!$this->onFlowCheckSizeBeforeCopy($key, $file))
        {
            return false;
        }
        $chunkDir = $this->getChunkDir($key);
        $chunkNumber = (int) $_REQUEST['flowChunkNumber'];
        $chunkFile = $chunkDir.'/'.$chunkNumber;
        return @copy($file['tmp_name'], $chunkFile);
    }
    
    private function onFlowCheckSizeBeforeCopy($key, $file)
    {
        $chunkDir = $this->getChunkDir($key);
        $already = FileUtil::dirsize($chunkDir);
        $additive = filesize($file['tmp_name']);
        $sumSize = $already + $additive;
        if ($sumSize > $this->maxsize)
        {
            $this->denyFlowFile($key, $file, "exceed size of {$this->maxsize}");
            return false;
        }
        return true;
    }
    
    private function onFlowFinishFile($key, $file)
    {
        $chunkDir = $this->getChunkDir($key);
        
        # Merge chunks to single temp file
        $finalFile = $chunkDir.'/temp';
        Filewalker::traverse($chunkDir, array($this, 'onMergeFile'), false, true, array($finalFile));
        
        # Write user chosen name to a file for later
        $nameFile = $chunkDir.'/name';
        @file_put_contents($nameFile, $file['name']);
        
        # Write mime type for later use
        $mimeFile = $chunkDir.'/mime';
        @file_put_contents($mimeFile, mime_content_type($chunkDir.'/temp'));
        
        # Run finishing tests to deny.
        if (false !== ($error = $this->onFlowFinishTests($key, $file)))
        {
            $this->denyFlowFile($key, $file, $error);
            return $error;
        }
        
        # Move single temp to chunk 0
        if (!@rename($finalFile, $chunkDir.'/0'))
        {
            return "Cannot move temp file.";
        }
        
        return false;
    }
    
    public function onMergeFile($entry, $fullpath, $args)
    {
        list($finalFile) = $args;
        @file_put_contents($finalFile, file_get_contents($fullpath), FILE_APPEND);
    }
    
    private function onFlowFinishTests($key, $file)
    {
        if (false !== ($error = $this->onFlowTestChecksum($key, $file)))
        {
            return $error;
        }
        if (false !== ($error = $this->onFlowTestMime($key, $file)))
        {
            return $error;
        }
        if (false !== ($error = $this->onFlowTestImageDimension($key, $file)))
        {
            return $error;
        }
        return false;
    }
    
    private function onFlowTestChecksum($key, $file)
    {
        return false;
    }
    
    private function onFlowTestMime($key, $file)
    {
        if (!($mime = @file_get_contents($this->getChunkDir($key).'/mime'))) {
            return "$key: No mime found for file";
        }
        if ((!in_array($mime, $this->mimes, true)) && (count($this->mimes)>0)) {
            return "$key: Unsupported MIME TYPE: $mime";
        }
        return false;
    }
    
    private function onFlowTestImageDimension($key, $file)
    {
        return false;
    }
    
}
