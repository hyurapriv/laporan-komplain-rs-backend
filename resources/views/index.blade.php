import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import Card from './Card';
import BarChart from './BarChart';
import { IoSendSharp } from "react-icons/io5";
import { FaTools, FaCheckCircle } from "react-icons/fa";
import { MdPendingActions, MdOutlineAccessTimeFilled } from "react-icons/md";

export default function CardKomplain() {
  const [dataKomplain, setDataKomplain] = useState({
    terkirim: 0,
    proses: 0,
    selesai: 0,
    ditunda: 0,
    responTime: 'N/A',
    total: 0
  });
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchKomplainData = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await axios.get('http://localhost:8000/api/komplain-data');
      setDataKomplain(response.data);
      setError(null);
    } catch (error) {
      console.error('Error fetching komplain data:', error);
      setError('Gagal memasukkan data komplain');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchKomplainData();
    const interval = setInterval(fetchKomplainData, 5 * 60 * 1000); // Refresh every 5 minutes

    return () => clearInterval(interval);
  }, [fetchKomplainData]);

  const cards = [
    { name: 'Terkirim', icon: <IoSendSharp size={18} color='#34C031' />, bgColor: 'bg-green', value: dataKomplain.terkirim },
    { name: 'Proses', icon: <FaTools size={18} color='#34C031' />, bgColor: 'bg-green', value: dataKomplain.proses },
    { name: 'Selesai', icon: <FaCheckCircle size={18} color='#34C031' />, bgColor: 'bg-green', value: dataKomplain.selesai },
    { name: 'Ditunda', icon: <MdPendingActions size={18} color='#34C031' />, bgColor: 'bg-green', value: dataKomplain.ditunda },
    { name: 'Respon Time', icon: <MdOutlineAccessTimeFilled size={18} color='#34C031' />, bgColor: 'bg-green', value: dataKomplain.responTime },
  ];

  if (isLoading) {
    return (
      <div className='flex justify-center items-center h-full'>
        <div className="animate-spin rounded-full h-32 w-32 border-t-2 border-b-2 border-green-500"></div>
      </div>
    );
  }

  if (error) {
    return <div className='text-red-500 text-center'>{error}</div>;
  }

  return (
    <section className='px-4 flex-1 pt-14'>
      <div className='flex justify-between'>
        <h3 className='text-xl font-bold text-slate-800'>Data Komplain Bulan Juli 2024</h3>
        <h3 className='text-xl font-bold text-slate-800 bg-slate-100 py-2 px-7 shadow-lg'>Juli 2024</h3>
      </div>
      <h3 className='ml-1 mt-10 text-lg font-bold text-slate-700'>Total Komplain: {dataKomplain.total}</h3>
      <div className='grid grid-cols-5 gap-4 mt-5'>
        {cards.map((card, index) => (
          <Card key={index} name={card.name} icon={card.icon} bgColor={card.bgColor} value={card.value} />
        ))}
      </div>
      <BarChart data={dataKomplain} />
    </section>
  );
}
