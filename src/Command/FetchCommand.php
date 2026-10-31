<?php

namespace App\Command;

use App\Service\XmlParser;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:fetch',
    description: 'Add a short description for your command',
)]
class FetchCommand
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly XmlParser $xmlParser,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Url')] string $url,
    ): int {
        $io->note(sprintf('Getting rss of rss from: %s', $url));

        $content = $this->httpClient->request('GET', $url)->getContent();
        $links = $this->xmlParser->parseXmlLinks($content);

        $io->success(sprintf('Found %d links', count($links)));

        // Responses are lazy: all requests are sent concurrently, then read in order
        $responses = [];
        foreach ($links as $link) {
            $responses[$link] = $this->httpClient->request('GET', $link);
        }

        foreach ($responses as $link => $response) {
            try {
                $statusCode = $response->getStatusCode();
                $html = $response->getContent(false);
            } catch (TransportExceptionInterface $e) {
                $io->writeln(sprintf('<error>[ERR]</error> %s (%s)', $link, $e->getMessage()));
                continue;
            }

            if (200 !== $statusCode) {
                $io->writeln(sprintf('<error>[%d]</error> %s', $statusCode, $link));
                continue;
            }

            $io->writeln('<info>[200]</info> '.$link);

            foreach ($this->xmlParser->parseHtmlFeedsUrls($html, $link) as $feed) {
                $io->writeln("\t-> ".$feed);
            }
        }

        $io->success('All done.');

        return Command::SUCCESS;
    }
}
