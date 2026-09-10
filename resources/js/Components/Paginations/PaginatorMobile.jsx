import Button from "../Button";

export default function PaginatorMobile({ paginator, onPageChange }) {

    return (
        <div className="mt-5 flex justify-center bg-primary-light rounded">
            {paginator.data.length < paginator.total && (
                <Button
                    onClick={() => onPageChange(paginator.next_page_url)}
                    disabled={!paginator.next_page_url}
                    className="flex items-center text-white opacity-75 py-2 text-sm font-bold tracking-tight"
                >
                    <span> Show more news</span>
                    <i className="fa-regular fa-circle-down ms-3"></i>
                </Button>
            )}
        </div>
    );
}
