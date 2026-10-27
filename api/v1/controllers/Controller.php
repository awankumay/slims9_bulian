<?php

/**
 * @author              : Waris Agung Widodo
 * @Date                : 2017-07-05 12:17:02
 * @Last Modified by    : ido
 * @Last Modified time  : 2017-07-05 13:53:28
 *
 * Copyright (C) 2017  Waris Agung Widodo (ido.alit@gmail.com)
 */

class Controller
{

    public function withJson($data)
    {
        header('Content-type: application/json');
        if (is_array($data)) {
            echo json_encode($data);
        } else {
            echo $data;
        }

        return true;
    }

    /**
     * Guard endpoints that expose library-wide statistics.
     *
     * These routes are only consumed by the staff dashboard, which now reads
     * the figures server-side. The HTTP endpoints are reached through the OPAC
     * (public) context, so a staff session ($_SESSION['uid']) is never present
     * there and an anonymous visitor is refused. This keeps aggregate counts
     * from being harvested without authentication.
     *
     * @return void
     */
    protected function requireStaff()
    {
        if (empty($_SESSION['uid'])) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-type: application/json');
            echo json_encode([
                'error' => 'Forbidden',
                'message' => 'Staff authentication required'
            ]);
            exit();
        }
    }

}