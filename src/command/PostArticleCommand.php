<?php
namespace clomery\command;

use CURLFile;
use clomery\markdown\LinkParse;
use clomery\remote\RemoteClass;
use suda\core\storage\FileStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostArticleCommand extends Command
{
    protected static $defaultName = 'post:article';

    protected function configure()
    {
        $this
        ->setDescription('post the markdown archive')
        ->addArgument('path', InputArgument::REQUIRED, 'article path')
        ->addOption('url', 'u', InputOption::VALUE_OPTIONAL, 'the server post api url')
        ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'the server post api token')
        ->addOption('force', 'f', InputOption::VALUE_NONE, 'force update post data')
        ->addOption('database', 'db', InputOption::VALUE_OPTIONAL, 'the path to save scan database', './clomery-data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $s = new FileStorage;
        $path = $input->getArgument('path');
        $path = $s->abspath($path);
        $name = \pathinfo($path, PATHINFO_FILENAME);
        $outputPath = $input->getOption('database');
        $outputPath = $s->path($outputPath);
        $force = $input->getOption('force');

        $url = $input->getOption('url');
        $token = $input->getOption('token');
        $remoteClass = new RemoteClass($url, $outputPath.'/session', [
            'Clomery-Token' => $token,
        ]);

        $io->text('upload to <info>'.$url.'</>');
        $io->newLine(2);

        $hash = \md5_file($path);

        if ($this->checkMd5($outputPath.'/posts/'.$name.'/version', $hash) === false || $force) {
            $this->getApplication()
           ->find('post:generate')
           ->run(new ArrayInput([ 'path' => $path,  '--database' => $outputPath, '--force'=> $force]), $output);
            $this->getApplication()
           ->find('post:analysis')
           ->run(new ArrayInput([ 'name' => $name,  '--database' => $outputPath,]), $output);
        } else {
            $io->text('uploaded article hash : <info>'.$hash.'</>');
            return 0;
        }
        
        // 读取文件信息
        $articlePath = $outputPath.'/posts/'.$name.'.json';
        $articleData = \json_decode($s->get($articlePath), true);
        $content = $articleData['content'];
        
        if (isset($articleData['meta']['categories'])) {
            $category = $articleData['meta']['categories'][count($articleData['meta']['categories'])-1] ?? '';
        } else {
            $category = null;
        }
        
        
        $articleId = $remoteClass->_call('save', [
            'title' => $articleData['meta']['title'] ?? 'untitiled',
            'slug' => $name,
            'excerpt' => $articleData['excerpt'] ?? '',
            'content' => $content,
            'category' => $category ,
            'create' => \date_create_from_format('Y-m-d H:i:s', $articleData['meta']['date'])->getTimestamp(),
            'tags' => $articleData['meta']['tags'] ?? null,
            'status' => 2,
        ]);

        $io->text('uploaded article id: <info>'.$articleId.'</>');
        $io->newLine(2);

        $replace = [];
        $imageJson =  $outputPath.'/posts/'.$name.'/image.json';
        $attachmentJson =  $outputPath.'/posts/'.$name.'/attachment.json';
        $nameJson =  $outputPath.'/posts/'.$name.'/name.json';
        $names = [];
        if ($s->exist($nameJson)) {
            $names = \json_decode($s->get($nameJson), true);
        }

        if ($s->exist($imageJson)) {
            $io->text('upload images');
            $articleImageData = \json_decode($s->get($imageJson), true);
            $progressBar = new ProgressBar($output, count($articleImageData));
            foreach ($articleImageData as $path) {
                $filePath = $outputPath.'/posts/'.$name.'/resource/' .$path;
                if ($s->exist($filePath)) {
                    $uploadedInfo = $remoteClass->_call('saveImage', [
                        'article' => $articleId,
                        'name' => $names[$path] ?? $path,
                        'image' => new CURLFile($filePath),
                    ]);
                    if (!empty($uploadedInfo)) {
                        $replace[$path] = $uploadedInfo;
                        $progressBar->advance();
                    }
                }
            }
            $progressBar->finish();
            $io->newLine(2);
        }
        
        if ($s->exist($attachmentJson)) {
            $io->text('upload attachment');
            $articleAttachmentData = \json_decode($s->get($attachmentJson), true);
            $progressBar = new ProgressBar($output, count($articleAttachmentData));
            foreach ($articleAttachmentData as $path) {
                $filePath = $s->abspath($outputPath.'/posts/'.$name.'/resource/' .$path);
                if ($s->exist($filePath)) {
                    $uploadedInfo = $remoteClass->_call('saveAttachment', [
                        'article' => $articleId,
                        'name' => $names[$path] ?? $path,
                        'attachment' => new CURLFile($filePath),
                    ]);
                    if (!empty($uploadedInfo)) {
                        $replace[$path] = $uploadedInfo;
                    }
                }
                
                $progressBar->advance();
            }
            $progressBar->finish();
            $io->newLine(2);
        }
        foreach ($replace as $path => $uploadedInfo) {
            $content = \str_replace(']('.$path.')', ']('.$uploadedInfo['url'].')', $content);
        }

        $articleId = $remoteClass->_call('save', [
            'title' => $articleData['meta']['title'] ?? 'untitiled',
            'slug' => $name,
            'excerpt' => $articleData['excerpt'] ?? '',
            'content' => $content,
            'category' => $category ,
            'create' => \date_create_from_format('Y-m-d H:i:s', $articleData['meta']['date'])->getTimestamp(),
            'tags' => $articleData['meta']['tags']?? null,
            'status' => 2,
        ]);
        $io->text('uploaded article resource : <info>'.$hash.'</>');
        \file_put_contents($outputPath.'/posts/'.$name.'/version', $hash);
    }

    protected function checkMd5(string $path, string $md5)
    {
        if (\file_exists($path)) {
            return \file_get_contents($path) == $md5;
        }
        return false;
    }
}
