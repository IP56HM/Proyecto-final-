<?php
// Consultas derivadas: compatibles con hosting sin permiso CREATE VIEW.
$ss_detalle_neto = <<<'SQL'
(SELECT d.id_detalle, d.id_venta, d.id_producto, d.cantidad, d.precio_unitario, d.subtotal,
       COALESCE(r.dev_cantidad,0) AS devueltas,
       (d.cantidad-COALESCE(r.dev_cantidad,0)) AS cantidad_neta,
       ROUND(d.subtotal-COALESCE(r.dev_cantidad,0)*d.precio_unitario,2) AS subtotal_neto
 FROM detalle_ventas d
 LEFT JOIN (SELECT id_detalle,SUM(cantidad) AS dev_cantidad FROM devoluciones
            WHERE id_detalle IS NOT NULL GROUP BY id_detalle) r ON r.id_detalle=d.id_detalle)
SQL;
$ss_ventas_netas = <<<'SQL'
(SELECT v.id_venta,v.id_usuario,v.fecha,v.total AS total_original,
       ROUND(v.total-COALESCE(r.dev_total,0),2) AS total_neto
 FROM ventas v
 LEFT JOIN (SELECT d.id_venta, SUM(d.precio_unitario * r.cantidad) AS dev_total
            FROM devoluciones r JOIN detalle_ventas d ON d.id_detalle=r.id_detalle
            WHERE r.id_detalle IS NOT NULL GROUP BY d.id_venta) r ON r.id_venta=v.id_venta)
SQL;
