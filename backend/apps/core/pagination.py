from rest_framework.pagination import PageNumberPagination
from rest_framework.response import Response


class Pagination(PageNumberPagination):
    page_size = 20
    page_size_query_param = "taille"
    max_page_size = 1000

    def get_paginated_response(self, data):
        return Response(
            {
                "count": self.page.paginator.count,
                "pages": self.page.paginator.num_pages,
                "page": self.page.number,
                "results": data,
            }
        )
