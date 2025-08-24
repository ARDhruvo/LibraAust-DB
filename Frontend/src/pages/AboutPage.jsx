import { useParams } from "react-router-dom";
import { useEffect, useState } from "react";
import ReactMarkdown from "react-markdown";

export default function AboutPage() {
  const { slug } = useParams();
  const [page, setPage] = useState(null);

  useEffect(() => {
    fetch(`http://localhost:8000/api/pages/${slug}`)
      .then((res) => res.json())
      .then((data) => setPage(data));
  }, [slug]);

  if (!page) return <p>Loading...</p>;

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">
        {slug === "kfr" ? "About KFR Library" : "Library Rules & Regulations"}
      </h1>

      <div className="prose">
        <ReactMarkdown>{page.description}</ReactMarkdown>
      </div>
    </div>
  );
}
