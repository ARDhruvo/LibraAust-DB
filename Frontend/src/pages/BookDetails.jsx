import { useParams, Link } from "react-router-dom";
import { useEffect, useState } from "react";

export default function BookDetails() {
  const { id } = useParams();
  const [book, setBook] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchBook() {
      try {
        const res = await fetch(`http://127.0.0.1:8000/api/books/${id}`);
        if (!res.ok) throw new Error("Book not found");
        const data = await res.json();
        setBook(data);
      } catch (err) {
        console.error(err);
        setBook(null);
      } finally {
        setLoading(false);
      }
    }
    fetchBook();
  }, [id]);

  if (loading) {
    return <div className="text-center py-10 text-gray-600">Loading book...</div>;
  }

  if (!book) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[70vh] text-center">
        <p className="text-gray-600 text-lg">Book not found.</p>
        <Link
          to="/resources/books"
          className="mt-6 px-5 py-2 bg-blue-600 text-white rounded-xl shadow hover:bg-blue-700 transition"
        >
          ← Back to Books
        </Link>
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center py-12 px-4">
      {/* Back button */}
      <div className="w-full max-w-5xl mb-6">
        <Link
          to="/resources/books"
          className="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition shadow-sm"
        >
          ← Back
        </Link>
      </div>

      <div className="w-full max-w-5xl bg-white shadow-xl rounded-2xl overflow-hidden grid md:grid-cols-2">
        {/* Book Cover */}
        <div className="p-6 flex justify-center items-center bg-gray-50">
          <img
            src={book.cover_url}
            alt={book.title}
            className="w-full max-w-xs rounded-xl shadow-lg"
          />
        </div>

        {/* Book Info */}
        <div className="p-8 flex flex-col">
          <h1 className="text-3xl font-bold mb-4">{book.title}</h1>
          <p className="text-lg text-gray-700 mb-2">
            <span className="font-semibold">Author:</span> {book.author}
          </p>
          <p className="text-gray-600 mb-2">
            <span className="font-semibold">Department:</span> {book.category}
          </p>
          <p className="text-gray-600 mb-2">
            <span className="font-semibold">Published:</span> {book.year}
          </p>
          <p className="text-gray-600 mb-4">
            <span className="font-semibold">Type:</span> {book.type}
          </p>

          {/* Description */}
          <p className="mt-6 text-gray-700 leading-relaxed">{book.description}</p>
        </div>
      </div>
    </div>
  );
}
