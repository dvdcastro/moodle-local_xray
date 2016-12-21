<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AWS validation helpers.
 *
 * @package   local_xray
 * @author    Darko Miletic
 * @copyright Copyright (c) 2016 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xray\local\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class validationaws
 * @package local_xray
 */
abstract class validationaws {
    /**
     * This test can indicate several issues:
     * Plugin is not configured
     * Plugin is not correctly configured (incorrect clientid, password or URL)
     * Lack of conectivity (external or internal conectivity issues)
     * Web service is down
     * Malformed responses
     *
     * @return bool|string[]
     * @throws \moodle_exception
     */
    public static function check_ws_connect() {
        $result   = [];
        $whenStr  = get_string('validate_when', wsapi::PLUGIN).' ';
        $reason   = '';
        $reason_fields = [];
        $colon = ': ';
        $break = '<br />';
        $fields_title = get_string('validate_check_fields', wsapi::PLUGIN).$colon.$break;
        
        $config = get_config('local_xray');
        if (empty($config)) {
            $result[] = get_string("error_wsapi_config_params_empty", wsapi::PLUGIN);
            return $result;
        }

        /** @var string[] $params */
        $params = [
              'xrayusername'
            , 'xraypassword'
            , 'xrayclientid'
            , 'xrayurl'
        ];

        foreach ($params as $property) {
            if (!isset($config->{$property}) || empty($config->{$property})) {
                $result[] = validationaws::strong(get_string("error_wsapi_config_{$property}", wsapi::PLUGIN));
            }
        }
        
        if(!empty($result)) {
            return $result;
        }

        try {
            // Testing login
            $reason = get_string('error_wsapi_reason_login', wsapi::PLUGIN);
            $reason_fields = ['xrayusername','xraypassword','xrayurl'];
            $loginRes = wsapi::login();
            if (!$loginRes) {
                throw new \moodle_exception(xrayws::instance()->errorinfo());
            }
            
            // If login fails, nothing else will work
            if(!empty($result)) {
               return $result;
            }
            
            // Testing the query endpoints
            $reason = get_string('error_wsapi_reason_accesstoken', wsapi::PLUGIN);
            $reason_fields = ['xrayclientid','xrayurl'];
            $tokenRes = wsapi::accesstoken();
            if (!$tokenRes) {
                throw new \moodle_exception(xrayws::instance()->errorinfo());
            }
            
            $reason = get_string('error_wsapi_reason_domaininfo', wsapi::PLUGIN);
            $reason_fields = ['xrayclientid','xrayurl'];
            $domainRes = wsapi::domaininfo();
            if (!$domainRes) {
                throw new \moodle_exception(xrayws::instance()->errorinfo());
            } else {
                
                $requiredKeys = array(
                    'name',
                    'courses',
                    'activecourses',
                    'analysedcourses',
                    'participants',
                    'instructors',
                    'totalreports'
                );
                
                $domainArr = (array) $domainRes->data;
                $numExistingKeys = count(array_intersect_key(array_flip($requiredKeys), $domainArr));
                $numReqKeys = count($requiredKeys);
                
                if($numExistingKeys !== $numReqKeys) {
                    $missingKeys = [];
                    foreach ($requiredKeys as $key) {
                        if(!array_key_exists($key, $domainArr)) {
                            $missingKeys[] = $key;
                        }
                    }
                    
                    $error = get_string("error_wsapi_domaininfo_incomplete",
                        wsapi::PLUGIN, implode(", ", $missingKeys));
                    throw new \moodle_exception($error);
                }
            }
            
            $reason = get_string('error_wsapi_reason_courses', wsapi::PLUGIN);
            $reason_fields = ['xrayclientid','xrayurl'];
            $coursesRes = wsapi::courses();
            if(!$coursesRes) {
                throw new \moodle_exception(xrayws::instance()->errorinfo());
            }
            
        } catch (\Exception $ex) {
            $html_fields = validationaws::listFields($reason_fields);
                
            $result[] = get_string("error_wsapi_exception",
                wsapi::PLUGIN, $whenStr.validationaws::strong($reason).$break.$fields_title.$html_fields.
                validationaws::service_info('ws_connect',$ex->getMessage()));
        }
        
        if(!empty($result)) {
            return $result;
        }
        
        return true;
    }

    /**
     * This test can indicate several issues:
     * Plugin is not configured
     * Plugin is not correctly configured (incorrect AWS credentials)
     * Lack of conectivity (external or internal conectivity issues)
     * S3 is down
     * S3 does not have correct permissions
     *
     * @return bool|string[]
     * @throws \moodle_exception
     */
    public static function check_s3_bucket() {
        $result   = [];
        $whenStr  = get_string('validate_when', wsapi::PLUGIN).' ';
        $reason   = '';
        $reason_fields = [];
        $colon = ': ';
        $break = '<br />';
        $fields_title = get_string('validate_check_fields', wsapi::PLUGIN).$colon.$break;
        
        global $CFG;
        $config = get_config('local_xray');
        if (empty($config)) {
            $result[] = get_string("error_wsapi_config_params_empty", wsapi::PLUGIN);
            return $result;
        }

        /** @var string[] $params */
        $params = [
              'awskey'
            , 'awssecret'
            , 's3bucket'
            , 's3bucketregion'
            , 's3protocol'
            , 's3uploadretry'
        ];

        foreach ($params as $property) {
            if (!isset($config->{$property}) || empty($config->{$property})) {
                $result[] = validationaws::strong(get_string("error_awssync_config_{$property}", wsapi::PLUGIN));
            }
        }

        if (!empty($result)) {
            return $result;
        }

        try {
            /* @noinspection PhpIncludeInspection */
            require_once($CFG->dirroot . "/local/xray/lib/vendor/aws/aws-autoloader.php");

            // Testing AWS client creation
            $reason = get_string('error_aws_reason_client_create', wsapi::PLUGIN);
            $reason_fields = ['s3bucketregion','s3protocol','s3uploadretry','awskey','awssecret'];
            /* @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
            /* @noinspection PhpUndefinedClassInspection */
            $s3 = new \Aws\S3\S3Client(
                [
                  'version'     => '2006-03-01'
                , 'region'      => $config->s3bucketregion
                , 'scheme'      => $config->s3protocol
                , 'retries'     => (int)$config->s3uploadretry
                , 'credentials' => [
                      'key'    => $config->awskey
                    , 'secret' => $config->awssecret
                    ]
                ]
            );

            /* @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
            /* @noinspection PhpUndefinedClassInspection */
            $task = new \local_xray\task\data_sync();
            $location = $task->get_storageprefix()."{$config->xrayusername}/";
            $objectKey = $location.'sample.test.upload.txt';
            $objectContent = "sample test: ".md5(uniqid(rand(), true));
            

            // Testing AWS object listing
            $reason = get_string('error_aws_reason_object_list', wsapi::PLUGIN);
            $reason_fields = ['s3bucket','s3bucketregion','s3protocol','s3uploadretry','awskey','awssecret'];
            // Try to list the directory and get no more than 1 items to speed things up.
            // It does not matter if there are no objects, we are checking the access rights here.
            $objects = $s3->listObjects(
                [
                      'Bucket'  => $config->s3bucket
                    , 'Prefix'  => $location
                    , 'MaxKeys' => 1
                ]
            );
            ($objects);

            // Now we try to upload simple file.
            $reason = get_string('error_aws_reason_upload_file', wsapi::PLUGIN);
            $reason_fields = ['s3bucket','s3bucketregion','s3protocol','s3uploadretry','awskey','awssecret'];
            $uploadfile = tmpfile();
            if ($uploadfile !== false) {
                fwrite($uploadfile, $objectContent);
                rewind($uploadfile);
                $uploadresult = $s3->upload(
                      $config->s3bucket
                    , $objectKey
                    , $uploadfile
                    , 'private'
                    , ['debug' => true]
                );
                $metadata = $uploadresult->get('@metadata');
                if ($metadata['statusCode'] !== 200) {
                    throw new \moodle_exception("Upload to S3 bucket failed!");
                }
            } else {
                throw new \moodle_exception("Unable to create temporary file!");
            }
            
            // Now we try to download simple file.
            $reason = get_string('error_aws_reason_download_file', wsapi::PLUGIN);
            $reason_fields = ['s3bucket','s3bucketregion','s3protocol','s3uploadretry','awskey','awssecret'];
            $awsObject = $s3->getObject(array(
                'Bucket' => $config->s3bucket,
                'Key'    => $objectKey
            ));
            
            if ($metadata['statusCode'] !== 200) {
                throw new \moodle_exception("Download from S3 bucket failed!");
            } else if($awsObject['Body'] != $objectContent) {
                throw new \moodle_exception("S3 bucket corrupt.");
            }
            
            // Now we try to erase simple file.
            $reason = get_string('error_aws_reason_erase_file', wsapi::PLUGIN);
            $reason_fields = ['s3bucket','s3bucketregion','s3protocol','s3uploadretry','awskey','awssecret'];
            $delResult = $s3->deleteObject(array(
                'Bucket' => $config->s3bucket,
                'Key'    => $objectKey
            ));
            
            $delMetadata = $delResult->get('@metadata');
            
            if ($delMetadata['statusCode'] !== 204) {
                throw new \moodle_exception("Deletion on S3 bucket failed!");
            } 

        } catch (\Exception $ex) {
            
            $html_fields = validationaws::listFields($reason_fields);
                
            $result[] = get_string("error_awssync_exception",
                wsapi::PLUGIN, $whenStr.validationaws::strong($reason).$break.$fields_title.$html_fields.
                validationaws::service_info('s3_bucket',$ex->getMessage()));
        }
        
        if (!empty($result)) {
            return $result;
        }

        return true;
    }

    

    /**
     * This test can indicate several issues:
     * Plugin is not configured
     * Plugin is not correctly configured (incorrect compression parameters)
     * System does not support compression as configured in plugin
     *
     * @return bool|string[]
     * @throws \moodle_exception
     */
    public static function check_compress() {
        $result   = [];
        $whenStr  = get_string('validate_when', wsapi::PLUGIN).' ';
        $reason   = '';
        $reason_fields = [];
        $sep = ':<br /><br />';
        $fields_title = get_string('validate_check_fields', wsapi::PLUGIN).$sep;
        $response_title = get_string('validate_service_response', wsapi::PLUGIN).$sep;
        
        $config = get_config('local_xray');
        if (empty($config)) {
            $result[] = get_string("error_wsapi_config_params_empty", wsapi::PLUGIN);
            return $result;
        }

        /** @var string[] $params */
        $params = [
              'enablepacker' => ['packertar']
            , 'exportlocation' => []
        ];

        $result = [];
        foreach ($params as $prop => $subprops) {
            if (!isset($config->{$prop})) {
                $result[] = validationaws::strong(get_string("error_compress_config_{$prop}", wsapi::PLUGIN));
            } else if ($config->{$prop} == true) {
                foreach ($subprops as $subprop) {
                    if (!isset($config->{$subprop}) 
                        || ( gettype($config->{$subprop}) == 'string' && empty($config->{$subprop}))) {
                        $result[] = validationaws::strong(get_string("error_compress_config_{$subprop}", wsapi::PLUGIN));
                    }
                }
            }
        }

        if (!empty($result)) {
            return $result;
        }

        try {
            // Export database and compress files
            $timeend = time() - (2 * MINSECS);
            $storage = new auto_clean();
            
            $file_list = [];
            data_export::export_csv(0, $timeend, $storage->get_directory());
            
            // Store list of files before compression
            $dir_list = $storage->listdir_as_array();
            foreach ($dir_list as $filepath) {
                $file_list[] = basename($filepath);
            }
            $num_files = count($file_list);
            
            // Execute compression
            $compressResult = data_export::compress($storage->get_dirbase(), $storage->get_dirname());
            
            if($compressResult !== TRUE) { // TGZ Compression
                list($compfile, $destfile) = $compressResult;
                
                $comp_files = (new \tgz_extractor($compfile))->list_files();
                $tgzfile_list = [];
                foreach ($comp_files as $cfile) {
                    $tgzfile_list[] = $cfile->pathname;
                }
                
                if(count(array_intersect($tgzfile_list, $file_list)) != $num_files){
                    throw new \moodle_exception(get_string('error_compress_files', wsapi::PLUGIN));
                }
                
            } else { // BZ2 Compression
                $bcomp_files = $storage->listdir_as_array();
                
                $bz2file_list = [];
                foreach ($bcomp_files as $bfile) {
                    $bz2file_list[] = basename($bfile, '.bz2');
                }
                
                if(count(array_intersect($bz2file_list, $file_list)) != $num_files){
                    throw new \moodle_exception(get_string('error_compress_files', wsapi::PLUGIN));
                }
            }
            
        } catch (Exception $e) {
            $result[] = get_string("error_compress_exception",
                wsapi::PLUGIN, $e->getMessage());
        }
        
        if (!empty($result)) {
            return $result;
        }

        return true;
    }
    
    /**
     * Creates and an unordered HTML list of xray fields
     * 
     * @param string[] $fields String identifies of the fields to be listed
     */
    private static function listFields($fields) {
        $res = '<ul>';
        
        foreach ($fields as $field) {
            $res .= '<li>'.validationaws::strong(get_string($field, wsapi::PLUGIN)).'</li>';
        }
        
        $res .= '</ul>';
        
        return $res;
    }
    
    /**
     * Wraps string with a strong html tag
     * 
     * @param string $str
     */
    private static function strong($str) {
        return '<strong>'.$str.'</strong>';
    }
    
    /**
     * Wraps string with a div html tag with a serviceinfo class
     * 
     * @param string $str
     */
    private static function service_info($service, $str) {
        $response_title = get_string('validate_service_response', wsapi::PLUGIN);
        
        return '<a id="'.$service.'" class="xray_service_info_btn" href>'.$response_title.'</a><br /><br />'
               .'<div id="'.$service.'_txt"class="xray_service_info">'.$str.'</div>';
    }
}