<?php

namespace CM3_Lib\Action\Badge\Format;

use CM3_Lib\util\badgeinfo;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\badge\format;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Search
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(private Responder $responder, private format $format, private badgeinfo $badgeinfo)
    {
    }

    /**
     * Action.
     *
     * @param ServerRequestInterface $request The request
     * @param ResponseInterface $response The response
     *
     * @return ResponseInterface The response
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Extract the form data from the request body
        $qp = $request->getQueryParams();
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        $whereParts = array(
            new SearchTerm('event_id', $request->getAttribute('event_id'))
          //new SearchTerm('active', 1)
        );

        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, 'id');

        $totalRows = 0;
        // Invoke the Domain with inputs and retain the result
        $data = $this->format->Search(array(), $whereParts, $pg['order'], $pg['limit'], $pg['offset'], $totalRows);
        $response = $response->withHeader('X-Total-Rows', (string)$totalRows);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
