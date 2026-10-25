<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\HttpClient;
use App\Service\XmlParser;

#[AsCommand(
    name: 'app:fetch',
    description: 'Add a short description for your command',
)]
class FetchCommand{
    public function __construct(
        protected HttpClient $httpClient,
        protected XmlParser $xmlParser
    )
    {

    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Url')] string $url,
    ): int {
        $io->note(sprintf('Getting rss of rss from: %s', $url));

        $response = $this->httpClient->get($url);
        $links = $this->xmlParser->parseXmlLinks($response->getBody()->getContents());

        $io->success(sprintf('Found %d links', count($links)));

        foreach ($links as $link) {
            $response = $this->httpClient->get($link);

            if( $response->getStatusCode() === 200) {
                $io->writeln("<info>[200]</info> " . $link);
            }
            else {
                $io->writeln("<error>[{$response->getStatusCode()}]</error> " . $link);
            }

            $feeds = $this->xmlParser->parseHtmlFeedsUrls($response->getBody()->getContents(), $link);

            foreach ($feeds as $feed) {
                $io->writeln("\t-> " . $feed);
            }
        }

        $io->success('All done.');

        return Command::SUCCESS;
    }
}
