// src/pages/SignUp.jsx
import { Formik, Form, Field } from "formik";
import { Link, useNavigate } from "react-router-dom";
import * as Yup from "yup";
import { toast } from "react-hot-toast";
import api from "../services/api"; // Import axios instance

// Create a custom TextField component since we removed the inputs folder
const TextField = ({ field, form: { touched, errors }, ...props }) => (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-1">
      {props.label}
    </label>
    <input
      {...field}
      {...props}
      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
        touched[field.name] && errors[field.name]
          ? "border-red-500"
          : "border-gray-300"
      }`}
    />
    {touched[field.name] && errors[field.name] && (
      <div className="text-red-500 text-sm mt-1">{errors[field.name]}</div>
    )}
  </div>
);

const SelectField = ({
  field,
  form: { touched, errors },
  children,
  ...props
}) => (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-1">
      {props.label}
    </label>
    <select
      {...field}
      {...props}
      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
        touched[field.name] && errors[field.name]
          ? "border-red-500"
          : "border-gray-300"
      }`}
    >
      {children}
    </select>
    {touched[field.name] && errors[field.name] && (
      <div className="text-red-500 text-sm mt-1">{errors[field.name]}</div>
    )}
  </div>
);

// Custom Checkbox component
// Updated CheckboxField component
const CheckboxField = ({
  field,
  form: { setFieldValue, touched, errors },
  ...props
}) => (
  <div className="flex items-center">
    <input
      {...field}
      {...props}
      type="checkbox"
      checked={field.value}
      onChange={(e) =>
        setFieldValue(field.name, e.target.checked ? "true" : "false")
      }
      className={`h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded ${
        touched[field.name] && errors[field.name] ? "border-red-500" : ""
      }`}
    />
    <label htmlFor={field.name} className="ml-2 block text-sm text-gray-900">
      {props.label}
    </label>
    {touched[field.name] && errors[field.name] && (
      <div className="text-red-500 text-sm mt-1">{errors[field.name]}</div>
    )}
  </div>
);

const SignUp = () => {
  const navigate = useNavigate();

  const departments = ["CSE", "EEE", "BBA", "ME", "TE", "CE", "IPE", "ARCH"];

  const semesters = ["1.1", "1.2", "2.1", "2.2", "3.1", "3.2", "4.1", "4.2"];

  const validationSchema = Yup.object().shape({
    name: Yup.string().required("Full name is required"),
    student_id: Yup.number()
      .typeError("Student ID must be a number")
      .required("Student ID is required"),
    department: Yup.string().required("Department is required"),
    semester: Yup.string().required("Semester is required"),
    phone: Yup.string().nullable(),
    email: Yup.string().email("Invalid email").required("Email is required"),
    studentship: Yup.boolean().default(true),
    password: Yup.string()
      .min(8, "Password must be at least 8 characters")
      .matches(/[a-z]/, "Password must contain at least one lowercase letter")
      .matches(/[A-Z]/, "Password must contain at least one uppercase letter")
      .matches(/[0-9]/, "Password must contain at least one number")
      .required("Password is required"),
    password_confirmation: Yup.string()
      .oneOf([Yup.ref("password"), null], "Passwords must match")
      .required("Please confirm your password"),
  });

  const handleSubmit = async (values, { setSubmitting, setErrors }) => {
    try {
      // Remove password fields before sending to students endpoint
      const { password, password_confirmation, ...studentData } = values;

      console.log("Sending student data:", studentData);

      const response = await api.post("/v1/students", studentData, {
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
      });

      toast.success("Registration successful!");
      navigate("/");
    } catch (error) {
      console.error("Registration error:", error);

      if (error.response?.data?.errors) {
        const laravelErrors = {};
        Object.entries(error.response.data.errors).forEach(
          ([key, messages]) => {
            laravelErrors[key] = messages[0];
          }
        );
        setErrors(laravelErrors);
      }

      const errorMessage =
        error.response?.data?.message || "Registration failed";
      toast.error(errorMessage);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
        {/* Header */}
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">LibraAust</h1>
          <h2 className="text-2xl font-semibold text-gray-800">
            Student Registration
          </h2>
          <p className="text-gray-600 mt-2">Create your library account</p>
        </div>

        <Formik
          initialValues={{
            name: "",
            student_id: "",
            department: "",
            semester: "",
            phone: "",
            email: "",
            studentship: true, // Default to true
            password: "",
            password_confirmation: "",
          }}
          validationSchema={validationSchema}
          onSubmit={handleSubmit}
        >
          {({ isSubmitting, errors, touched }) => (
            <Form className="space-y-4">
              {/* Student Information */}
              <Field
                name="name"
                component={TextField}
                label="Full Name *"
                placeholder="Enter your full name"
              />

              <Field
                name="student_id"
                component={TextField}
                label="Student ID *"
                type="number"
                placeholder="Enter your student ID"
              />

              <Field
                name="department"
                component={SelectField}
                label="Department *"
              >
                <option value="">Select Department</option>
                {departments.map((dept) => (
                  <option key={dept} value={dept}>
                    {dept}
                  </option>
                ))}
              </Field>

              <Field name="semester" component={SelectField} label="Semester *">
                <option value="">Select Semester</option>
                {semesters.map((sem) => (
                  <option key={sem} value={sem}>
                    {sem}
                  </option>
                ))}
              </Field>

              <Field
                name="phone"
                component={TextField}
                label="Phone Number (Optional)"
                placeholder="Enter your phone number"
              />

              {/* Studentship Checkbox */}
              <Field
                name="studentship"
                component={CheckboxField}
                label="I am currently a student"
              />

              {/* User Account Information */}
              <Field
                name="email"
                component={TextField}
                label="Email *"
                type="email"
                placeholder="Enter your email"
              />

              <Field
                name="password"
                component={TextField}
                label="Password *"
                type="password"
                placeholder="Create a password"
              />

              <Field
                name="password_confirmation"
                component={TextField}
                label="Confirm Password *"
                type="password"
                placeholder="Confirm your password"
              />

              <button
                type="submit"
                disabled={isSubmitting}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isSubmitting ? "Creating Account..." : "Create Account"}
              </button>
            </Form>
          )}
        </Formik>

        <div className="text-center mt-6">
          <p className="text-gray-600">
            Already have an account?{" "}
            <Link
              to="/signin"
              className="text-blue-600 hover:text-blue-700 font-semibold"
            >
              Sign in
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
};

export default SignUp;
